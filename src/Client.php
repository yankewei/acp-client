<?php

declare(strict_types=1);

namespace Yankewei\AcpClient;

use JsonException;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Exception\JsonRpcException;
use Yankewei\AcpClient\Exception\TransportException;
use Yankewei\AcpClient\JsonRpc\Request;
use Yankewei\AcpClient\JsonRpc\Response;
use Yankewei\AcpClient\Transport\TransportInterface;

final class Client
{
    /** @var array<string, Response> */
    private array $pendingResponses = [];

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly float $defaultTimeout = 30.0,
    ) {
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function initialize(array $params = []): array
    {
        $result = $this->call(
            'initialize',
            array_replace_recursive($this->defaultInitializeParams(), $params),
        );

        return $this->expectArrayResult($result, 'initialize');
    }

    /**
     * @return array{
     *     protocolVersion: int,
     *     clientCapabilities: array{
     *         fs: array{readTextFile: bool, writeTextFile: bool},
     *         terminal: bool
     *     },
     *     clientInfo: array{name: string, title: string, version: string}
     * }
     */
    private function defaultInitializeParams(): array
    {
        return [
            'protocolVersion' => 1,
            'clientCapabilities' => [
                'fs' => [
                    'readTextFile' => false,
                    'writeTextFile' => false,
                ],
                'terminal' => false,
            ],
            'clientInfo' => [
                'name' => 'yankewei/acp-client',
                'title' => 'ACP Client for PHP',
                'version' => '0.1.0',
            ],
        ];
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function authenticate(string $methodId, ?float $timeout = null): array
    {
        return $this->expectArrayResult(
            $this->call('authenticate', ['methodId' => $methodId], $timeout),
            'authenticate',
        );
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function logout(?float $timeout = null): array
    {
        return $this->expectArrayResult($this->call('logout', [], $timeout), 'logout');
    }

    /**
     * @param array<int, array<string, mixed>> $mcpServers
     * @param string[] $additionalDirectories
     *
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionNew(
        string $cwd,
        array $mcpServers = [],
        array $additionalDirectories = [],
        ?float $timeout = null,
    ): array {
        return $this->expectArrayResult(
            $this->call(
                'session/new',
                $this->sessionSetupParams($cwd, $mcpServers, $additionalDirectories),
                $timeout,
            ),
            'session/new',
        );
    }

    /**
     * @param array<int, array<string, mixed>> $mcpServers
     * @param string[] $additionalDirectories
     *
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionLoad(
        string $sessionId,
        string $cwd,
        array $mcpServers = [],
        array $additionalDirectories = [],
        ?float $timeout = null,
    ): mixed {
        return $this->call(
            'session/load',
            ['sessionId' => $sessionId] + $this->sessionSetupParams($cwd, $mcpServers, $additionalDirectories),
            $timeout,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $mcpServers
     * @param string[] $additionalDirectories
     *
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionResume(
        string $sessionId,
        string $cwd,
        array $mcpServers = [],
        array $additionalDirectories = [],
        ?float $timeout = null,
    ): array {
        return $this->expectArrayResult(
            $this->call(
                'session/resume',
                ['sessionId' => $sessionId] + $this->sessionSetupParams($cwd, $mcpServers, $additionalDirectories),
                $timeout,
            ),
            'session/resume',
        );
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionClose(string $sessionId, ?float $timeout = null): array
    {
        return $this->expectArrayResult(
            $this->call('session/close', ['sessionId' => $sessionId], $timeout),
            'session/close',
        );
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionList(?string $cwd = null, ?string $cursor = null, ?float $timeout = null): array
    {
        $params = [];

        if ($cwd !== null) {
            $params['cwd'] = $cwd;
        }

        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        return $this->expectArrayResult(
            $this->call('session/list', $params, $timeout),
            'session/list',
        );
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionDelete(string $sessionId, ?float $timeout = null): array
    {
        return $this->expectArrayResult(
            $this->call('session/delete', ['sessionId' => $sessionId], $timeout),
            'session/delete',
        );
    }

    /**
     * @param string|array<int, array<string, mixed>> $prompt
     *
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionPrompt(string $sessionId, string|array $prompt, ?float $timeout = null): array
    {
        return $this->expectArrayResult(
            $this->call(
                'session/prompt',
                [
                    'sessionId' => $sessionId,
                    'prompt' => $this->normalizePrompt($prompt),
                ],
                $timeout,
            ),
            'session/prompt',
        );
    }

    /**
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionCancel(string $sessionId): void
    {
        $this->notify('session/cancel', ['sessionId' => $sessionId]);
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function setConfigOption(
        string $sessionId,
        string $configId,
        string $value,
        ?float $timeout = null,
    ): array {
        return $this->expectArrayResult(
            $this->call(
                'session/set_config_option',
                [
                    'sessionId' => $sessionId,
                    'configId' => $configId,
                    'value' => $value,
                ],
                $timeout,
            ),
            'session/set_config_option',
        );
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function setMode(string $sessionId, string $modeId, ?float $timeout = null): mixed
    {
        return $this->call(
            'session/set_mode',
            [
                'sessionId' => $sessionId,
                'modeId' => $modeId,
            ],
            $timeout,
        );
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function call(string $method, array $params = [], ?float $timeout = null): mixed
    {
        $this->transport->open();

        $request = new Request($method, Request::nextId(), $params);
        $this->transport->send($request->toJson());

        $response = $this->waitForResponse($request->getId(), $timeout ?? $this->defaultTimeout);

        if ($response === null) {
            throw new TransportException('Timeout waiting for response');
        }

        if ($response->hasError()) {
            $error = $response->getError();
            throw new JsonRpcException($error->getCode(), $error->getMessage(), $error->getData());
        }

        return $response->getResult();
    }

    /**
     * @throws JsonException
     * @throws TransportException
     */
    public function notify(string $method, array $params = []): void
    {
        $this->transport->open();

        $payload = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ];

        $this->transport->send(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<int, array<string, mixed>> $mcpServers
     * @param string[] $additionalDirectories
     * @return array{cwd: string, mcpServers: array<int, array<string, mixed>>, additionalDirectories?: string[]}
     */
    private function sessionSetupParams(string $cwd, array $mcpServers, array $additionalDirectories): array
    {
        $params = [
            'cwd' => $cwd,
            'mcpServers' => $mcpServers,
        ];

        if ($additionalDirectories !== []) {
            $params['additionalDirectories'] = array_values($additionalDirectories);
        }

        return $params;
    }

    /**
     * @param string|array<int, array<string, mixed>> $prompt
     * @return array<int, array<string, mixed>>
     */
    private function normalizePrompt(string|array $prompt): array
    {
        if (is_string($prompt)) {
            return [
                [
                    'type' => 'text',
                    'text' => $prompt,
                ],
            ];
        }

        return array_values($prompt);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AcpException
     */
    private function expectArrayResult(mixed $result, string $method): array
    {
        if (!is_array($result)) {
            throw new AcpException(
                "Invalid {$method} response: result is not an object/array",
            );
        }

        return $result;
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    private function waitForResponse(int|string $id, float $timeout): ?Response
    {
        $pendingKey = $this->responseKey($id);
        if (array_key_exists($pendingKey, $this->pendingResponses)) {
            $response = $this->pendingResponses[$pendingKey];
            unset($this->pendingResponses[$pendingKey]);

            return $response;
        }

        $end = microtime(true) + $timeout;

        do {
            $remaining = $end - microtime(true);
            if ($remaining <= 0.0) {
                return null;
            }

            $line = $this->transport->receive($remaining);

            if ($line === null) {
                return null;
            }

            if ($this->isJsonRpcNotification($line)) {
                continue;
            }

            $response = Response::fromJson($line);

            if ($response->getId() === $id) {
                return $response;
            }

            $responseId = $response->getId();
            if (is_int($responseId) || is_string($responseId)) {
                $this->pendingResponses[$this->responseKey($responseId)] = $response;
            }
        } while (true);
    }

    private function responseKey(int|string $id): string
    {
        return gettype($id) . ':' . $id;
    }

    private function isJsonRpcNotification(string $message): bool
    {
        try {
            $data = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return is_array($data)
            && !array_is_list($data)
            && ($data['jsonrpc'] ?? null) === '2.0'
            && is_string($data['method'] ?? null)
            && !array_key_exists('id', $data);
    }
}
