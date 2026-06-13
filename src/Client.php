<?php

declare(strict_types=1);

namespace Yankewei\AcpClient;

use JsonException;
use Throwable;
use Yankewei\AcpClient\Dto\InitializeResult;
use Yankewei\AcpClient\Dto\PromptResult;
use Yankewei\AcpClient\Dto\Session;
use Yankewei\AcpClient\Dto\SessionListResult;
use Yankewei\AcpClient\Event\Notification;
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

    /** @var array<int, callable(Notification): void> */
    private array $notificationListeners = [];

    /** @var array<string, array<int, callable(Notification): void>> */
    private array $methodListeners = [];

    /** @var array<string, array<int, callable(array<string, mixed>): mixed>> */
    private array $requestHandlers = [];

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly float $defaultTimeout = 30.0,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function initialize(array $params = []): InitializeResult
    {
        $result = $this->call(
            'initialize',
            array_replace_recursive($this->defaultInitializeParams(), $params),
        );

        return InitializeResult::fromArray($this->expectArrayResult($result, 'initialize'));
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
     * @return array<string, mixed>
     *
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
     * @return array<string, mixed>
     *
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
    ): Session {
        return Session::fromArray(
            $this->expectArrayResult(
                $this->call(
                    'session/new',
                    $this->sessionSetupParams($cwd, $mcpServers, $additionalDirectories),
                    $timeout,
                ),
                'session/new',
            ),
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
    ): Session {
        return Session::fromArray(
            $this->expectArrayResult(
                $this->call(
                    'session/resume',
                    ['sessionId' => $sessionId] + $this->sessionSetupParams($cwd, $mcpServers, $additionalDirectories),
                    $timeout,
                ),
                'session/resume',
            ),
        );
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionClose(string $sessionId, ?float $timeout = null): Session
    {
        return Session::fromArray(
            $this->expectArrayResult(
                $this->call('session/close', ['sessionId' => $sessionId], $timeout),
                'session/close',
            ),
        );
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionList(?string $cwd = null, ?string $cursor = null, ?float $timeout = null): SessionListResult
    {
        /** @var array<string, mixed> $params */
        $params = [];

        if ($cwd !== null) {
            $params['cwd'] = $cwd;
        }

        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        return SessionListResult::fromArray(
            $this->expectArrayResult(
                $this->call('session/list', $params, $timeout),
                'session/list',
            ),
        );
    }

    /**
     * @return array<string, mixed>
     *
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
    public function sessionPrompt(string $sessionId, string|array $prompt, ?float $timeout = null): PromptResult
    {
        return PromptResult::fromArray(
            $this->expectArrayResult(
                $this->call(
                    'session/prompt',
                    [
                        'sessionId' => $sessionId,
                        'prompt' => $this->normalizePrompt($prompt),
                    ],
                    $timeout,
                ),
                'session/prompt',
            ),
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
     * @return array<string, mixed>
     *
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
     * @param callable(Notification): void $listener
     */
    public function onNotification(callable $listener): void
    {
        $this->notificationListeners[] = $listener;
    }

    /**
     * @param callable(Notification): void $listener
     */
    public function offNotification(callable $listener): void
    {
        $this->notificationListeners = array_values(
            array_filter(
                $this->notificationListeners,
                static fn (callable $existing): bool => $existing !== $listener,
            ),
        );
    }

    /**
     * @param callable(Notification): void $listener
     */
    public function on(string $method, callable $listener): void
    {
        $this->methodListeners[$method][] = $listener;
    }

    /**
     * @param callable(Notification): void $listener
     */
    public function off(string $method, callable $listener): void
    {
        if (!array_key_exists($method, $this->methodListeners)) {
            return;
        }

        $this->methodListeners[$method] = array_values(
            array_filter(
                $this->methodListeners[$method],
                static fn (callable $existing): bool => $existing !== $listener,
            ),
        );
    }

    /**
     * @param callable(array<string, mixed>): mixed $handler
     */
    public function onRequest(string $method, callable $handler): void
    {
        $this->requestHandlers[$method][] = $handler;
    }

    /**
     * @param callable(array<string, mixed>): mixed $handler
     */
    public function offRequest(string $method, callable $handler): void
    {
        if (!array_key_exists($method, $this->requestHandlers)) {
            return;
        }

        $this->requestHandlers[$method] = array_values(
            array_filter(
                $this->requestHandlers[$method],
                static fn (callable $existing): bool => $existing !== $handler,
            ),
        );
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

        /** @var array<string, mixed> $result */
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

            $data = $this->parseJsonLine($line);
            if ($data !== null) {
                $notification = $this->toNotification($data);
                if ($notification !== null) {
                    $this->dispatch($notification);
                    continue;
                }

                if ($this->isServerRequest($data)) {
                    $this->handleServerRequest($data);
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

    private function dispatch(Notification $notification): void
    {
        foreach ($this->notificationListeners as $listener) {
            $listener($notification);
        }

        foreach ($this->methodListeners[$notification->getMethod()] ?? [] as $listener) {
            $listener($notification);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonLine(string $message): ?array
    {
        try {
            $data = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
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
     * @param array<string, mixed> $data
     */
    private function handleServerRequest(array $data): void
    {
        $id = $data['id'];
        if (!is_int($id) && !is_string($id)) {
            return;
        }

        $method = $data['method'];
        if (!is_string($method)) {
            $this->sendError($id, -32600, 'Invalid Request');
            return;
        }

        $handlers = $this->requestHandlers[$method] ?? [];
        if ($handlers === []) {
            $this->sendError($id, -32601, "Method not found: {$method}");
            return;
        }

        $params = $data['params'] ?? [];
        if (!is_array($params) || array_is_list($params)) {
            $params = [];
        }

        try {
            $result = $handlers[0]($params);
            $this->sendResponse($id, $result);
        } catch (Throwable $e) {
            $this->sendError($id, -32603, $e->getMessage());
        }
    }

    private function sendResponse(int|string $id, mixed $result): void
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];

        $this->transport->send(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function sendError(int|string $id, int $code, string $message, mixed $data = null): void
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($data !== null) {
            $payload['error']['data'] = $data;
        }

        $this->transport->send(json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
