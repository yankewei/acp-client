<?php

declare(strict_types=1);

namespace Yankewei\AcpClient;

use JsonException;
use Yankewei\AcpClient\Dto\InitializeResult;
use Yankewei\AcpClient\Dto\PromptResult;
use Yankewei\AcpClient\Dto\Session;
use Yankewei\AcpClient\Dto\SessionConfigOptionsResult;
use Yankewei\AcpClient\Dto\SessionListResult;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Exception\TransportException;
use Yankewei\AcpClient\Util\Assert;

final class Acp
{
    private ?InitializeResult $initializeResult = null;

    public function __construct(
        private readonly JsonRpcPeer $rpc,
        private readonly AgentRequestDispatcher $requests,
        private readonly ProtocolValidator $validator,
    ) {}

    /**
     * @param array<string, mixed> $params
     *
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function initialize(array $params = []): InitializeResult
    {
        $result = $this->rpc->call('initialize', array_replace_recursive($this->defaultInitializeParams(), $params));

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
            'clientCapabilities' => $this->requests->clientCapabilities(),
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
        if ($this->validator->isStrict()) {
            $initializeResult = $this->validator->requireInitialized('authenticate', $this->initializeResult);
            if ($initializeResult->getAuthMethod($methodId) === null) {
                throw new AcpException("Cannot call authenticate: agent did not advertise auth method {$methodId}");
            }
        }

        return $this->expectArrayResult(
            $this->rpc->call('authenticate', ['methodId' => $methodId], $timeout),
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
        if ($this->validator->isStrict()) {
            $initializeResult = $this->validator->requireInitialized('logout', $this->initializeResult);
            if (!$initializeResult->supportsLogout()) {
                throw new AcpException('Cannot call logout: agent did not advertise auth.logout');
            }
        }

        return $this->expectArrayResult($this->rpc->call('logout', [], $timeout), 'logout');
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
        $this->validator->validateSessionSetup(
            'session/new',
            $cwd,
            $mcpServers,
            $additionalDirectories,
            $this->initializeResult,
        );

        return Session::fromArray($this->expectArrayResult(
            $this->rpc->call(
                'session/new',
                $this->sessionSetupParams($cwd, $mcpServers, $additionalDirectories),
                $timeout,
            ),
            'session/new',
        ));
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
        if ($this->validator->isStrict()) {
            if (!$this->validator->requireInitialized('session/load', $this->initializeResult)->supportsLoadSession()) {
                throw new AcpException('Cannot call session/load: agent did not advertise loadSession');
            }
        }

        $this->validator->validateSessionSetup(
            'session/load',
            $cwd,
            $mcpServers,
            $additionalDirectories,
            $this->initializeResult,
        );

        return $this->rpc->call(
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
        if ($this->validator->isStrict()) {
            if (!$this->validator->requireInitialized(
                'session/resume',
                $this->initializeResult,
            )->supportsSessionResume()) {
                throw new AcpException(
                    'Cannot call session/resume: agent did not advertise sessionCapabilities.resume',
                );
            }
        }

        $this->validator->validateSessionSetup(
            'session/resume',
            $cwd,
            $mcpServers,
            $additionalDirectories,
            $this->initializeResult,
        );

        return Session::fromArray($this->expectArrayResult(
            $this->rpc->call(
                'session/resume',
                ['sessionId' => $sessionId] + $this->sessionSetupParams($cwd, $mcpServers, $additionalDirectories),
                $timeout,
            ),
            'session/resume',
        ));
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionClose(string $sessionId, ?float $timeout = null): Session
    {
        if ($this->validator->isStrict()) {
            $initializeResult = $this->validator->requireInitialized('session/close', $this->initializeResult);
            if (!$initializeResult->supportsSessionClose()) {
                throw new AcpException('Cannot call session/close: agent did not advertise sessionCapabilities.close');
            }
        }

        return Session::fromArray($this->expectArrayResult(
            $this->rpc->call('session/close', ['sessionId' => $sessionId], $timeout),
            'session/close',
        ));
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionList(?string $cwd = null, ?string $cursor = null, ?float $timeout = null): SessionListResult
    {
        $this->validator->validateSessionList($cwd, $this->initializeResult);

        /** @var array<string, mixed> $params */
        $params = [];

        if ($cwd !== null) {
            $params['cwd'] = $cwd;
        }

        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        return SessionListResult::fromArray($this->expectArrayResult(
            $this->rpc->call('session/list', $params, $timeout),
            'session/list',
        ));
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
        if ($this->validator->isStrict()) {
            if (!$this->validator->requireInitialized(
                'session/delete',
                $this->initializeResult,
            )->supportsSessionDelete()) {
                throw new AcpException(
                    'Cannot call session/delete: agent did not advertise sessionCapabilities.delete',
                );
            }
        }

        return $this->expectArrayResult(
            $this->rpc->call('session/delete', ['sessionId' => $sessionId], $timeout),
            'session/delete',
        );
    }

    /**
     * @param string|array<int, array<string, mixed>> $prompt
     * @param array<array-key, mixed> $meta
     *
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionPrompt(
        string $sessionId,
        string|array $prompt,
        ?float $timeout = null,
        array $meta = [],
    ): PromptResult {
        $meta = $meta === [] ? [] : Assert::object($meta, 'Invalid session/prompt params: _meta must be an object');

        $prompt = $this->normalizePrompt($prompt);
        $this->validator->validatePrompt('session/prompt', $prompt, $this->initializeResult);

        $params = [
            'sessionId' => $sessionId,
            'prompt' => $prompt,
        ];

        if ($meta !== []) {
            $params['_meta'] = $meta;
        }

        return PromptResult::fromArray($this->expectArrayResult(
            $this->rpc->call('session/prompt', $params, $timeout),
            'session/prompt',
        ));
    }

    /**
     * Runs a slash command through the protocol-defined session/prompt path.
     *
     * @param array<array-key, mixed> $meta
     *
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionSlashCommand(
        string $sessionId,
        string $command,
        ?string $input = null,
        ?float $timeout = null,
        array $meta = [],
    ): PromptResult {
        $command = ltrim($command, characters: '/');

        if ($command === '') {
            throw new AcpException('Invalid slash command: command must not be empty');
        }

        $text = '/' . $command;

        if ($input !== null && $input !== '') {
            $text .= ' ' . $input;
        }

        return $this->sessionPrompt($sessionId, $text, $timeout, $meta);
    }

    /**
     * @throws JsonException
     * @throws TransportException
     */
    public function sessionCancel(string $sessionId): void
    {
        $this->rpc->notify('session/cancel', ['sessionId' => $sessionId]);
        $this->requests->cancelPendingPermissionRequests($sessionId);
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
    ): SessionConfigOptionsResult {
        return SessionConfigOptionsResult::fromArray($this->expectArrayResult(
            $this->rpc->call(
                'session/set_config_option',
                [
                    'sessionId' => $sessionId,
                    'configId' => $configId,
                    'value' => $value,
                ],
                $timeout,
            ),
            'session/set_config_option',
        ));
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function setMode(string $sessionId, string $modeId, ?float $timeout = null): SessionConfigOptionsResult
    {
        return $this->setConfigOption($sessionId, 'mode', $modeId, $timeout);
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
     *
     * @throws AcpException
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

        if ($this->validator->isStrict() && !array_is_list($prompt)) {
            throw new AcpException('Invalid session/prompt params: prompt must be a list of content blocks');
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
            throw new AcpException("Invalid {$method} response: result is not an object/array");
        }

        /** @var array<string, mixed> $result */
        return $result;
    }
}
