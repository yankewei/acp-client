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
use Yankewei\AcpClient\Util\Path;

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

    /** @var (callable(string, array<string, mixed>): mixed)|null */
    private $anyRequestHandler = null;

    private ?InitializeResult $initializeResult = null;

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly float $defaultTimeout = 30.0,
        private readonly bool $strictProtocol = true,
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

        $initializeResult = InitializeResult::fromArray($this->expectArrayResult($result, 'initialize'));
        $this->initializeResult = $initializeResult;

        return $initializeResult;
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
        if ($this->strictProtocol) {
            $initializeResult = $this->requireInitialized('authenticate');
            if ($initializeResult->getAuthMethod($methodId) === null) {
                throw new AcpException(
                    "Cannot call authenticate: agent did not advertise auth method {$methodId}",
                );
            }
        }

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
        if ($this->strictProtocol) {
            $this->requireInitialized('logout');
            if (!$this->initializeResult?->supportsLogout()) {
                throw new AcpException('Cannot call logout: agent did not advertise auth.logout');
            }
        }

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
        $this->validateSessionSetup('session/new', $cwd, $mcpServers, $additionalDirectories);

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
        if ($this->strictProtocol) {
            if (!$this->requireInitialized('session/load')->supportsLoadSession()) {
                throw new AcpException('Cannot call session/load: agent did not advertise loadSession');
            }
        }

        $this->validateSessionSetup('session/load', $cwd, $mcpServers, $additionalDirectories);

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
        if ($this->strictProtocol) {
            if (!$this->requireInitialized('session/resume')->supportsSessionResume()) {
                throw new AcpException('Cannot call session/resume: agent did not advertise sessionCapabilities.resume');
            }
        }

        $this->validateSessionSetup('session/resume', $cwd, $mcpServers, $additionalDirectories);

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
        if ($this->strictProtocol) {
            $initializeResult = $this->requireInitialized('session/close');
            if (!$initializeResult->supportsSessionClose()) {
                throw new AcpException('Cannot call session/close: agent did not advertise sessionCapabilities.close');
            }
        }

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
        if ($this->strictProtocol) {
            if (!$this->requireInitialized('session/list')->supportsSessionList()) {
                throw new AcpException('Cannot call session/list: agent did not advertise sessionCapabilities.list');
            }

            if ($cwd !== null && !Path::isAbsolutePath($cwd)) {
                throw new AcpException('Invalid session/list params: cwd must be an absolute path');
            }
        }

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
        if ($this->strictProtocol) {
            if (!$this->requireInitialized('session/delete')->supportsSessionDelete()) {
                throw new AcpException('Cannot call session/delete: agent did not advertise sessionCapabilities.delete');
            }
        }

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
        if ($this->strictProtocol && is_array($prompt) && !array_is_list($prompt)) {
            throw new AcpException('Invalid session/prompt params: prompt must be a list of content blocks');
        }

        $prompt = $this->normalizePrompt($prompt);
        $this->validatePrompt('session/prompt', $prompt);

        return PromptResult::fromArray(
            $this->expectArrayResult(
                $this->call(
                    'session/prompt',
                    [
                        'sessionId' => $sessionId,
                        'prompt' => $prompt,
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
     * Register a fallback handler for agent requests that do not have a
     * method-specific handler. This is useful for agent-specific permission or
     * ask-user methods whose exact names are not known ahead of time.
     *
     * Setting a new handler replaces any previously registered fallback handler.
     *
     * @param callable(string, array<string, mixed>): mixed $handler
     */
    public function onAnyRequest(callable $handler): void
    {
        $this->anyRequestHandler = $handler;
    }

    /**
     * Remove the fallback handler only if it is currently registered.
     *
     * @param callable(string, array<string, mixed>): mixed $handler
     */
    public function offAnyRequest(callable $handler): void
    {
        if ($this->anyRequestHandler === $handler) {
            $this->anyRequestHandler = null;
        }
    }

    /**
     * @param array<int, mixed> $mcpServers
     * @param string[] $additionalDirectories
     *
     * @throws AcpException
     */
    private function validateSessionSetup(
        string $method,
        string $cwd,
        array $mcpServers,
        array $additionalDirectories,
    ): void {
        if (!$this->strictProtocol) {
            return;
        }

        $initializeResult = $this->requireInitialized($method);

        if (!Path::isAbsolutePath($cwd)) {
            throw new AcpException("Invalid {$method} params: cwd must be an absolute path");
        }

        if ($additionalDirectories !== [] && !$initializeResult->supportsAdditionalDirectories()) {
            throw new AcpException(
                "Cannot call {$method} with additionalDirectories: agent did not advertise sessionCapabilities.additionalDirectories",
            );
        }

        foreach ($additionalDirectories as $directory) {
            if (!Path::isAbsolutePath($directory)) {
                throw new AcpException(
                    "Invalid {$method} params: additionalDirectories entries must be absolute paths",
                );
            }
        }

        $this->validateMcpServers($method, $mcpServers, $initializeResult);
    }

    /**
     * @throws AcpException
     */
    private function requireInitialized(string $method): InitializeResult
    {
        if ($this->initializeResult === null) {
            throw new AcpException("Cannot call {$method} before initialize() in strict protocol mode");
        }

        return $this->initializeResult;
    }

    /**
     * @param array<int, mixed> $mcpServers
     *
     * @throws AcpException
     */
    private function validateMcpServers(
        string $method,
        array $mcpServers,
        InitializeResult $initializeResult,
    ): void {
        if (!array_is_list($mcpServers)) {
            throw new AcpException("Invalid {$method} params: mcpServers must be a list");
        }

        foreach ($mcpServers as $index => $server) {
            $server = $this->requireObjectValue($method, "mcpServers[{$index}]", $server);

            $type = $server['type'] ?? 'stdio';
            if (!is_string($type)) {
                throw new AcpException("Invalid {$method} params: mcpServers[{$index}].type must be a string");
            }

            match ($type) {
                'stdio' => $this->validateStdioMcpServer($method, $index, $server),
                'http' => $this->validateHttpMcpServer($method, $index, $server, $initializeResult),
                'sse' => $this->validateSseMcpServer($method, $index, $server, $initializeResult),
                default => throw new AcpException(
                    "Invalid {$method} params: mcpServers[{$index}].type must be stdio, http, or sse",
                ),
            };
        }
    }

    /**
     * @param array<string, mixed> $server
     *
     * @throws AcpException
     */
    private function validateStdioMcpServer(string $method, int $index, array $server): void
    {
        $this->requireStringField($method, "mcpServers[{$index}].name", $server, 'name');
        $command = $this->requireStringField($method, "mcpServers[{$index}].command", $server, 'command');
        if (!Path::isAbsolutePath($command)) {
            throw new AcpException(
                "Invalid {$method} params: mcpServers[{$index}].command must be an absolute path",
            );
        }

        $this->requireStringListField($method, "mcpServers[{$index}].args", $server, 'args');

        $this->validateNameValueList($method, "mcpServers[{$index}].env", $server['env'] ?? null);
    }

    /**
     * @param array<string, mixed> $server
     *
     * @throws AcpException
     */
    private function validateHttpMcpServer(
        string $method,
        int $index,
        array $server,
        InitializeResult $initializeResult,
    ): void {
        if (!$initializeResult->supportsMcpHttp()) {
            throw new AcpException(
                "Cannot call {$method} with HTTP MCP server: agent did not advertise mcpCapabilities.http",
            );
        }

        $this->requireStringField($method, "mcpServers[{$index}].name", $server, 'name');
        $this->requireStringField($method, "mcpServers[{$index}].url", $server, 'url');
        $this->validateNameValueList(
            $method,
            "mcpServers[{$index}].headers",
            $server['headers'] ?? null,
        );
    }

    /**
     * @param array<string, mixed> $server
     *
     * @throws AcpException
     */
    private function validateSseMcpServer(
        string $method,
        int $index,
        array $server,
        InitializeResult $initializeResult,
    ): void {
        if (!$initializeResult->supportsMcpSse()) {
            throw new AcpException(
                "Cannot call {$method} with SSE MCP server: agent did not advertise mcpCapabilities.sse",
            );
        }

        $this->requireStringField($method, "mcpServers[{$index}].name", $server, 'name');
        $this->requireStringField($method, "mcpServers[{$index}].url", $server, 'url');
        $this->validateNameValueList(
            $method,
            "mcpServers[{$index}].headers",
            $server['headers'] ?? null,
        );
    }

    /**
     * @param array<mixed, mixed> $data
     *
     * @throws AcpException
     */
    private function requireStringField(string $method, string $label, array $data, string $key): string
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key]) || $data[$key] === '') {
            throw new AcpException("Invalid {$method} params: {$label} must be a non-empty string");
        }

        return $data[$key];
    }

    /**
     * @param array<mixed, mixed> $data
     *
     * @throws AcpException
     */
    private function requireStringListField(string $method, string $label, array $data, string $key): void
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key]) || !array_is_list($data[$key])) {
            throw new AcpException("Invalid {$method} params: {$label} must be a list of strings");
        }

        foreach ($data[$key] as $value) {
            if (!is_string($value)) {
                throw new AcpException("Invalid {$method} params: {$label} must be a list of strings");
            }
        }
    }

    /**
     * @throws AcpException
     */
    private function validateNameValueList(string $method, string $label, mixed $value): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new AcpException("Invalid {$method} params: {$label} must be a list of name/value objects");
        }

        foreach ($value as $index => $entry) {
            $entry = $this->requireObjectValue($method, "{$label}[{$index}]", $entry);

            $this->requireStringField($method, "{$label}[{$index}].name", $entry, 'name');
            $this->requireStringField($method, "{$label}[{$index}].value", $entry, 'value');
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AcpException
     */
    private function requireObjectValue(string $method, string $label, mixed $value): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new AcpException("Invalid {$method} params: {$label} must be an object");
        }

        $object = [];
        foreach ($value as $key => $entry) {
            if (!is_string($key)) {
                throw new AcpException("Invalid {$method} params: {$label} must be an object");
            }

            $object[$key] = $entry;
        }

        return $object;
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
     * @param array<int, mixed> $prompt
     *
     * @throws AcpException
     */
    private function validatePrompt(string $method, array $prompt): void
    {
        if (!$this->strictProtocol) {
            return;
        }

        $initializeResult = $this->requireInitialized($method);

        foreach ($prompt as $index => $block) {
            $block = $this->requireObjectValue($method, "prompt[{$index}]", $block);
            $type = $block['type'] ?? null;
            if (!is_string($type)) {
                throw new AcpException("Invalid {$method} params: prompt[{$index}].type must be a string");
            }

            $this->validateOptionalObjectField($method, "prompt[{$index}].annotations", $block, 'annotations');

            match ($type) {
                'text' => $this->validateTextContentBlock($method, $block, $index),
                'resource_link' => $this->validateResourceLinkContentBlock($method, $block, $index),
                'image' => $this->validateCapabilityContentBlock(
                    $method,
                    $block,
                    $index,
                    'image',
                    $initializeResult->supportsPromptImage(),
                    'promptCapabilities.image',
                ),
                'audio' => $this->validateCapabilityContentBlock(
                    $method,
                    $block,
                    $index,
                    'audio',
                    $initializeResult->supportsPromptAudio(),
                    'promptCapabilities.audio',
                ),
                'resource' => $this->validateEmbeddedContextContentBlock($method, $initializeResult, $block, $index),
                default => throw new AcpException(
                    "Invalid {$method} params: prompt[{$index}].type is not a supported content block type",
                ),
            };
        }
    }

    /**
     * @param array<string, mixed> $block
     *
     * @throws AcpException
     */
    private function validateTextContentBlock(string $method, array $block, int $index): void
    {
        if (!array_key_exists('text', $block) || !is_string($block['text'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].text must be a string");
        }
    }

    /**
     * @param array<string, mixed> $block
     *
     * @throws AcpException
     */
    private function validateResourceLinkContentBlock(string $method, array $block, int $index): void
    {
        if (!array_key_exists('uri', $block) || !is_string($block['uri'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].uri must be a string");
        }

        if (!array_key_exists('name', $block) || !is_string($block['name'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].name must be a string");
        }

        $this->validateOptionalStringField($method, "prompt[{$index}].mimeType", $block, 'mimeType');
        $this->validateOptionalStringField($method, "prompt[{$index}].title", $block, 'title');
        $this->validateOptionalStringField($method, "prompt[{$index}].description", $block, 'description');
        $this->validateOptionalIntField($method, "prompt[{$index}].size", $block, 'size');
    }

    /**
     * @param array<string, mixed> $block
     *
     * @throws AcpException
     */
    private function validateCapabilityContentBlock(
        string $method,
        array $block,
        int $index,
        string $type,
        bool $supported,
        string $capability,
    ): void {
        if (!$supported) {
            throw new AcpException(
                "Cannot call {$method} with {$type} content: agent did not advertise {$capability}",
            );
        }

        if (!array_key_exists('data', $block) || !is_string($block['data'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].data must be a string");
        }

        if (!array_key_exists('mimeType', $block) || !is_string($block['mimeType'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].mimeType must be a string");
        }

        if ($type === 'image') {
            $this->validateOptionalStringField($method, "prompt[{$index}].uri", $block, 'uri');
        }
    }

    /**
     * @param array<string, mixed> $block
     *
     * @throws AcpException
     */
    private function validateEmbeddedContextContentBlock(
        string $method,
        InitializeResult $initializeResult,
        array $block,
        int $index,
    ): void {
        if (!$initializeResult->supportsPromptEmbeddedContext()) {
            throw new AcpException(
                "Cannot call {$method} with resource content: agent did not advertise promptCapabilities.embeddedContext",
            );
        }

        if (!array_key_exists('resource', $block)) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].resource must be an object");
        }

        $resource = $this->requireObjectValue($method, "prompt[{$index}].resource", $block['resource']);

        if (!array_key_exists('uri', $resource) || !is_string($resource['uri'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].resource.uri must be a string");
        }

        $hasText = array_key_exists('text', $resource);
        $hasBlob = array_key_exists('blob', $resource);
        if (!$hasText && !$hasBlob) {
            throw new AcpException(
                "Invalid {$method} params: prompt[{$index}].resource must include text or blob",
            );
        }

        if ($hasText && $hasBlob) {
            throw new AcpException(
                "Invalid {$method} params: prompt[{$index}].resource cannot include both text and blob",
            );
        }

        if ($hasText && !is_string($resource['text'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].resource.text must be a string");
        }

        if ($hasBlob && !is_string($resource['blob'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].resource.blob must be a string");
        }

        $this->validateOptionalStringField($method, "prompt[{$index}].resource.mimeType", $resource, 'mimeType');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    private function validateOptionalStringField(string $method, string $label, array $data, string $key): void
    {
        if (array_key_exists($key, $data) && !is_string($data[$key])) {
            throw new AcpException("Invalid {$method} params: {$label} must be a string");
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    private function validateOptionalIntField(string $method, string $label, array $data, string $key): void
    {
        if (array_key_exists($key, $data) && !is_int($data[$key])) {
            throw new AcpException("Invalid {$method} params: {$label} must be an integer");
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    private function validateOptionalObjectField(string $method, string $label, array $data, string $key): void
    {
        if (!array_key_exists($key, $data)) {
            return;
        }

        $this->requireObjectValue($method, $label, $data[$key]);
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

        $methodHandler = $this->requestHandlers[$method][0] ?? null;
        $anyHandler = $this->anyRequestHandler;

        if ($methodHandler === null && $anyHandler === null) {
            $this->sendError($id, -32601, "Method not found: {$method}");
            return;
        }

        $params = $data['params'] ?? [];
        if (!is_array($params) || array_is_list($params)) {
            $params = [];
        }

        try {
            $result = $methodHandler !== null
                ? $methodHandler($params)
                : $anyHandler($method, $params);
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
