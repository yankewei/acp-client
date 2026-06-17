<?php

declare(strict_types=1);

namespace Yankewei\AcpClient;

use JsonException;
use Yankewei\AcpClient\Event\Notification;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Exception\JsonRpcException;
use Yankewei\AcpClient\Exception\TransportException;
use Yankewei\AcpClient\JsonRpc\Request;
use Yankewei\AcpClient\JsonRpc\Response;
use Yankewei\AcpClient\Transport\TransportInterface;

final class JsonRpcPeer
{
    /** @var array<string, Response> */
    private array $pendingResponses = [];

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly NotificationDispatcher $notifications,
        private readonly AgentRequestDispatcher $requests,
        private readonly float $defaultTimeout = 30.0,
    ) {}

    /**
     * @param array<string, mixed> $params
     *
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
            if ($error === null) {
                throw new AcpException('Invalid JSON-RPC response: error is missing');
            }

            throw new JsonRpcException($error->getCode(), $error->getMessage(), $error->getData());
        }

        return $response->getResult();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function callExtension(string $method, array $params = [], ?float $timeout = null): mixed
    {
        $this->validateExtensionMethod($method);

        return $this->call($method, $params, $timeout);
    }

    /**
     * @param array<string, mixed> $params
     *
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
     * @param array<string, mixed> $params
     *
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function notifyExtension(string $method, array $params = []): void
    {
        $this->validateExtensionMethod($method);

        $this->notify($method, $params);
    }

    /**
     * @throws AcpException
     */
    private function validateExtensionMethod(string $method): void
    {
        if (!str_starts_with($method, '_')) {
            throw new AcpException('Invalid extension method: method must start with "_"');
        }
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

            $data = $this->parseJsonLine($line);
            if ($data !== null) {
                $notification = $this->toNotification($data);
                if ($notification !== null) {
                    $this->notifications->dispatch($notification);
                    continue;
                }

                if ($this->isServerRequest($data)) {
                    $this->requests->handleServerRequest(
                        $data,
                        function (int|string $responseId, mixed $result): void {
                            $this->sendResponse($responseId, $result);
                        },
                        function (int|string $responseId, int $code, string $message, mixed $errorData = null): void {
                            $this->sendError($responseId, $code, $message, $errorData);
                        },
                    );
                    continue;
                }
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

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonLine(string $message): ?array
    {
        try {
            $data = json_decode($message, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($data) || array_is_list($data)) {
            return null;
        }

        if (($data['jsonrpc'] ?? null) !== '2.0') {
            return null;
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function toNotification(array $data): ?Notification
    {
        if (array_key_exists('id', $data)) {
            return null;
        }

        $method = $data['method'] ?? null;
        if (!is_string($method)) {
            return null;
        }

        $params = $data['params'] ?? [];
        if (!is_array($params) || array_is_list($params)) {
            $params = [];
        }

        /** @var array<string, mixed> $params */
        return new Notification($method, $params);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isServerRequest(array $data): bool
    {
        if (!array_key_exists('id', $data)) {
            return false;
        }

        $id = $data['id'];
        if (!is_int($id) && !is_string($id)) {
            return false;
        }

        $method = $data['method'] ?? null;

        return is_string($method);
    }

    /**
     * @throws JsonException
     * @throws TransportException
     */
    private function sendResponse(int|string $id, mixed $result): void
    {
        $this->transport->send(json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @throws JsonException
     * @throws TransportException
     */
    private function sendError(int|string $id, int $code, string $message, mixed $data = null): void
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        $this->transport->send(json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ], JSON_THROW_ON_ERROR));
    }
}
