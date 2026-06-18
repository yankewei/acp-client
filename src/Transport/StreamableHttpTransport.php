<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Transport;

use Yankewei\AcpClient\Exception\TransportException;

/**
 * Synchronous batch transport for the draft ACP Streamable HTTP mapping.
 *
 * Each {@see send()} performs a single blocking HTTP POST and queues every
 * JSON-RPC message found in that response body (plain JSON or SSE `data:`
 * events). {@see receive()} drains that queue without blocking: because
 * messages can only arrive as part of a POST response, there is no async
 * producer between calls, so an empty queue means the expected response was
 * not present in the HTTP body and {@see receive()} returns null immediately
 * regardless of $timeout. JsonRpcPeer then reports "Timeout waiting for
 * response", which for this transport signals a missing-in-body condition
 * rather than an elapsed wait.
 *
 * Consequences of the synchronous batch model:
 * - Server-initiated notifications that arrive outside a call's POST response
 *   are not delivered (there is no long-lived streaming connection).
 * - A request whose response is not packed into the same HTTP body fails
 *   immediately instead of waiting out $timeout.
 *
 * Deployment-specific auth, retries, and long-lived streaming semantics are
 * intentionally left to custom TransportInterface implementations.
 */
final class StreamableHttpTransport implements TransportInterface
{
    private bool $open = false;

    /** @var string[] */
    private array $receivedMessages = [];

    /**
     * @param array{
     *     url: string,
     *     headers?: array<string, string>,
     *     timeout?: float
     * } $config
     */
    public function __construct(
        private readonly array $config,
        private readonly ?StreamableHttpClientInterface $httpClient = null,
    ) {}

    public function open(): void
    {
        $url = $this->url();
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new TransportException('Invalid streamable HTTP transport URL');
        }

        $this->open = true;
    }

    public function send(string $message): void
    {
        $this->ensureOpen();

        $headers = $this->headers();
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json, text/event-stream';

        $response = $this->client()->post($this->url(), $message, $headers, $this->timeout());
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new TransportException("Streamable HTTP request failed with status {$statusCode}");
        }

        $this->queueResponseMessages($response->getBody(), $response->getContentType());
    }

    /**
     * Returns the next queued message from the last {@see send()} POST body,
     * or null when the queue is drained. $timeout is not consulted because this
     * transport has no async producer between calls: an empty queue means the
     * expected response was not in the HTTP body, so waiting cannot produce it.
     */
    public function receive(float $timeout = 0.0): ?string
    {
        return array_shift($this->receivedMessages);
    }

    public function close(): void
    {
        $this->open = false;
        $this->receivedMessages = [];
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    private function ensureOpen(): void
    {
        if (!$this->isOpen()) {
            throw new TransportException('Transport is not open');
        }
    }

    private function client(): StreamableHttpClientInterface
    {
        return $this->httpClient ?? new NativeStreamableHttpClient();
    }

    private function url(): string
    {
        return $this->config['url'];
    }

    private function timeout(): float
    {
        return $this->config['timeout'] ?? 30.0;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return $this->config['headers'] ?? [];
    }

    private function queueResponseMessages(string $body, string $contentType): void
    {
        if (trim($body) === '') {
            return;
        }

        if (str_contains($contentType, 'text/event-stream')) {
            $this->queueSseMessages($body);
            return;
        }

        $this->receivedMessages[] = trim($body);
    }

    private function queueSseMessages(string $body): void
    {
        $events = preg_split("/\r?\n\r?\n/", trim($body));
        if ($events === false) {
            return;
        }

        foreach ($events as $event) {
            $data = [];
            $lines = preg_split("/\r?\n/", $event);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $line) {
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }

                $data[] = ltrim(substr($line, strlen('data:')));
            }

            if ($data !== []) {
                $this->receivedMessages[] = implode("\n", $data);
            }
        }
    }
}
