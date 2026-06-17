<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Yankewei\AcpClient\Client;
use Yankewei\AcpClient\Dto\FileSystem\ReadTextFileRequest;
use Yankewei\AcpClient\Dto\FileSystem\ReadTextFileResult;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileRequest;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileResult;
use Yankewei\AcpClient\Dto\RequestPermission;
use Yankewei\AcpClient\Dto\RequestPermissionOutcome;
use Yankewei\AcpClient\Event\Notification;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Exception\JsonRpcException;
use Yankewei\AcpClient\Exception\TransportException;
use Yankewei\AcpClient\JsonRpc\Request;
use Yankewei\AcpClient\Transport\StdioTransport;

final class ClientTest extends TestCase
{
    protected function setUp(): void
    {
        $reflection = new ReflectionClass(Request::class);
        $property = $reflection->getProperty('idCounter');
        $property->setValue(null, 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $json): array
    {
        $decoded = json_decode($json, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $sent
     *
     * @return array<string, mixed>
     */
    private static function paramsOf(array $sent): array
    {
        self::assertIsArray($sent['params']);

        /** @var array<string, mixed> $params */
        $params = $sent['params'];

        return $params;
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return array<string, mixed>
     */
    private static function getArray(array $array, string $key): array
    {
        self::assertIsArray($array[$key]);

        /** @var array<string, mixed> $value */
        $value = $array[$key];

        return $value;
    }

    public function testCallReturnsResult(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['status' => 'ok'],
        ]);

        $client = new Client($transport, 1.0, false);

        $result = $client->call('initialize');

        static::assertSame(['status' => 'ok'], $result);
        static::assertCount(1, $transport->sent);

        $sent = self::decode($transport->sent[0]);
        static::assertSame('2.0', $sent['jsonrpc']);
        static::assertSame('initialize', $sent['method']);
        static::assertIsInt($sent['id']);
    }

    public function testInitializeSendsDefaultAcpParams(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => 1,
                'agentCapabilities' => [],
            ],
        ]);

        $client = new Client($transport, 1.0, false);

        $result = $client->initialize();
        static::assertSame(1, $result->getProtocolVersion());
        static::assertSame([], $result->getAgentCapabilities());

        $sent = self::decode($transport->sent[0]);
        static::assertSame('initialize', $sent['method']);

        $params = self::paramsOf($sent);
        static::assertSame(1, $params['protocolVersion']);

        $clientCapabilities = self::getArray($params, 'clientCapabilities');
        $fs = self::getArray($clientCapabilities, 'fs');
        static::assertFalse($fs['readTextFile']);
        static::assertFalse($fs['writeTextFile']);
        static::assertFalse($clientCapabilities['terminal']);

        $clientInfo = self::getArray($params, 'clientInfo');
        static::assertSame('yankewei/acp-client', $clientInfo['name']);
    }

    public function testInitializeAllowsParamOverrides(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => 1,
                'agentCapabilities' => [],
            ],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->initialize([
            'clientCapabilities' => [
                'terminal' => true,
            ],
            'clientInfo' => [
                'name' => 'custom-client',
            ],
        ]);

        $sent = self::decode($transport->sent[0]);

        $params = self::paramsOf($sent);
        $clientCapabilities = self::getArray($params, 'clientCapabilities');
        static::assertTrue($clientCapabilities['terminal']);

        $clientInfo = self::getArray($params, 'clientInfo');
        static::assertSame('custom-client', $clientInfo['name']);
        static::assertSame('ACP Client for PHP', $clientInfo['title']);
    }

    public function testAuthenticateCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['ok' => true]);
        $client = new Client($transport, 1.0, false);

        static::assertSame(['ok' => true], $client->authenticate('login'));

        $sent = $this->sentMessage($transport);
        static::assertSame('authenticate', $sent['method']);
        static::assertSame(['methodId' => 'login'], $sent['params']);
    }

    public function testStrictProtocolAuthenticatesAdvertisedMethod(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => 1,
                'agentCapabilities' => [],
                'authMethods' => [
                    [
                        'id' => 'login',
                        'name' => 'Login',
                    ],
                ],
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => [],
        ]);

        $client = new Client($transport, 1.0, true);
        $client->initialize();

        static::assertSame([], $client->authenticate('login'));

        $sent = self::decode($transport->sent[1]);
        static::assertSame('authenticate', $sent['method']);
        static::assertSame(['methodId' => 'login'], $sent['params']);
    }

    public function testStrictProtocolRejectsUnadvertisedAuthMethod(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call authenticate: agent did not advertise auth method login');

        $client->authenticate('login');
    }

    public function testStrictProtocolRequiresInitializeBeforeAuthenticate(): void
    {
        $client = new Client(new FakeTransport(), 1.0, true);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call authenticate before initialize() in strict protocol mode');

        $client->authenticate('login');
    }

    public function testLogoutCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult([]);
        $client = new Client($transport, 1.0, false);

        static::assertSame([], $client->logout());

        $sent = $this->sentMessage($transport);
        static::assertSame('logout', $sent['method']);
        static::assertSame([], $sent['params']);
    }

    public function testSessionNewCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['sessionId' => 'sess_1']);
        $client = new Client($transport, 1.0, false);

        $session = $client->sessionNew('/repo', [['name' => 'fs']], ['/shared']);
        static::assertSame('sess_1', $session->getSessionId());

        $sent = $this->sentMessage($transport);
        static::assertSame('session/new', $sent['method']);
        static::assertSame('/repo', self::paramsOf($sent)['cwd']);
        static::assertSame([['name' => 'fs']], self::paramsOf($sent)['mcpServers']);
        static::assertSame(['/shared'], self::paramsOf($sent)['additionalDirectories']);
    }

    public function testSessionLoadCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(null);
        $client = new Client($transport, 1.0, false);

        static::assertNull($client->sessionLoad('sess_1', '/repo'));

        $sent = $this->sentMessage($transport);
        static::assertSame('session/load', $sent['method']);
        static::assertSame('sess_1', self::paramsOf($sent)['sessionId']);
        static::assertSame('/repo', self::paramsOf($sent)['cwd']);
        static::assertSame([], self::paramsOf($sent)['mcpServers']);
        static::assertArrayNotHasKey('additionalDirectories', self::paramsOf($sent));
    }

    public function testSessionResumeCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['ready' => true]);
        $client = new Client($transport, 1.0, false);

        $session = $client->sessionResume('sess_1', '/repo');
        static::assertNull($session->getSessionId());

        $sent = $this->sentMessage($transport);
        static::assertSame('session/resume', $sent['method']);
        static::assertSame('sess_1', self::paramsOf($sent)['sessionId']);
        static::assertSame('/repo', self::paramsOf($sent)['cwd']);
    }

    public function testSessionCloseCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult([]);
        $client = new Client($transport, 1.0, false);

        $session = $client->sessionClose('sess_1');
        static::assertNull($session->getSessionId());

        $sent = $this->sentMessage($transport);
        static::assertSame('session/close', $sent['method']);
        static::assertSame(['sessionId' => 'sess_1'], $sent['params']);
    }

    public function testStrictProtocolRequiresInitializeBeforeSessionSetup(): void
    {
        $client = new Client(new FakeTransport(), 1.0, true);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/new before initialize() in strict protocol mode');

        $client->sessionNew('/repo');
    }

    public function testStrictProtocolIsEnabledByDefault(): void
    {
        $client = new Client(new FakeTransport(), 1.0);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/new before initialize() in strict protocol mode');

        $client->sessionNew('/repo');
    }

    public function testStrictProtocolAllowsAdvertisedSessionSetup(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => 1,
                'agentCapabilities' => [
                    'sessionCapabilities' => [
                        'additionalDirectories' => [],
                    ],
                    'mcpCapabilities' => [
                        'http' => true,
                        'sse' => true,
                    ],
                ],
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => ['sessionId' => 'sess_1'],
        ]);

        $client = new Client($transport, 1.0, true);
        $client->initialize();
        $session = $client->sessionNew(
            '/repo',
            [
                [
                    'name' => 'filesystem',
                    'command' => '/usr/local/bin/mcp-server',
                    'args' => ['--stdio'],
                    'env' => [['name' => 'TOKEN', 'value' => 'secret']],
                ],
                [
                    'type' => 'http',
                    'name' => 'api',
                    'url' => 'https://example.com/mcp',
                    'headers' => [],
                ],
                [
                    'type' => 'sse',
                    'name' => 'events',
                    'url' => 'https://example.com/sse',
                    'headers' => [['name' => 'X-API-Key', 'value' => 'secret']],
                ],
            ],
            ['/shared'],
        );

        static::assertSame('sess_1', $session->getSessionId());
    }

    public function testStrictProtocolRejectsRelativeCwd(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/new params: cwd must be an absolute path');

        $client->sessionNew('repo');
    }

    public function testStrictProtocolRejectsAdditionalDirectoriesWithoutCapability(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Cannot call session/new with additionalDirectories: agent did not advertise sessionCapabilities.additionalDirectories',
        );

        $client->sessionNew('/repo', [], ['/shared']);
    }

    public function testStrictProtocolRejectsLoadResumeAndCloseWithoutCapabilities(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/load: agent did not advertise loadSession');

        $client->sessionLoad('sess_1', '/repo');
    }

    public function testStrictProtocolRejectsResumeWithoutCapability(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/resume: agent did not advertise sessionCapabilities.resume');

        $client->sessionResume('sess_1', '/repo');
    }

    public function testStrictProtocolRejectsCloseWithoutCapability(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/close: agent did not advertise sessionCapabilities.close');

        $client->sessionClose('sess_1');
    }

    public function testStrictProtocolRejectsInvalidMcpServerShape(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/new params: mcpServers[0].command must be an absolute path');

        $client->sessionNew('/repo', [
            [
                'name' => 'filesystem',
                'command' => 'mcp-server',
                'args' => ['--stdio'],
            ],
        ]);
    }

    public function testStrictProtocolRejectsStdioMcpWithoutEnv(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Invalid session/new params: mcpServers[0].env must be a list of name/value objects',
        );

        $client->sessionNew('/repo', [
            [
                'name' => 'filesystem',
                'command' => '/usr/local/bin/mcp-server',
                'args' => ['--stdio'],
            ],
        ]);
    }

    public function testStrictProtocolRejectsHttpMcpWithoutCapability(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Cannot call session/new with HTTP MCP server: agent did not advertise mcpCapabilities.http',
        );

        $client->sessionNew('/repo', [
            [
                'type' => 'http',
                'name' => 'api',
                'url' => 'https://example.com/mcp',
                'headers' => [],
            ],
        ]);
    }

    public function testSessionListCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['sessions' => [], 'nextCursor' => 'next']);
        $client = new Client($transport, 1.0, false);

        $result = $client->sessionList('/repo', 'cursor');
        static::assertSame([], $result->getSessions());
        static::assertSame('next', $result->getNextCursor());

        $sent = $this->sentMessage($transport);
        static::assertSame('session/list', $sent['method']);
        static::assertSame(['cwd' => '/repo', 'cursor' => 'cursor'], $sent['params']);
    }

    public function testStrictProtocolRequiresInitializeBeforeSessionList(): void
    {
        $client = new Client(new FakeTransport(), 1.0);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/list before initialize() in strict protocol mode');

        $client->sessionList();
    }

    public function testStrictProtocolRejectsSessionListWithoutCapability(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/list: agent did not advertise sessionCapabilities.list');

        $client->sessionList();
    }

    public function testStrictProtocolRejectsRelativeSessionListCwd(): void
    {
        $transport = $this->initializedStrictTransport([
            'sessionCapabilities' => ['list' => []],
        ]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/list params: cwd must be an absolute path');

        $client->sessionList('repo');
    }

    public function testStrictProtocolAllowsAdvertisedSessionList(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => 1,
                'agentCapabilities' => [
                    'sessionCapabilities' => ['list' => []],
                ],
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => [
                'sessions' => [
                    [
                        'sessionId' => 'sess_1',
                        'cwd' => '/repo',
                        'title' => 'List support',
                    ],
                ],
            ],
        ]);

        $client = new Client($transport, 1.0);
        $client->initialize();
        $result = $client->sessionList('/repo', 'cursor');

        static::assertSame('sess_1', $result->getSessionInfos()[0]->getSessionId());

        $sent = self::decode($transport->sent[1]);
        static::assertSame('session/list', $sent['method']);
        static::assertSame(['cwd' => '/repo', 'cursor' => 'cursor'], $sent['params']);
    }

    public function testSessionDeleteCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult([]);
        $client = new Client($transport, 1.0, false);

        static::assertSame([], $client->sessionDelete('sess_1'));

        $sent = $this->sentMessage($transport);
        static::assertSame('session/delete', $sent['method']);
        static::assertSame(['sessionId' => 'sess_1'], $sent['params']);
    }

    public function testStrictProtocolRequiresInitializeBeforeSessionDelete(): void
    {
        $client = new Client(new FakeTransport(), 1.0);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/delete before initialize() in strict protocol mode');

        $client->sessionDelete('sess_1');
    }

    public function testStrictProtocolRejectsSessionDeleteWithoutCapability(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/delete: agent did not advertise sessionCapabilities.delete');

        $client->sessionDelete('sess_1');
    }

    public function testSessionPromptAcceptsText(): void
    {
        $transport = $this->transportWithResult(['stopReason' => 'end_turn']);
        $client = new Client($transport, 1.0, false);

        $result = $client->sessionPrompt('sess_1', 'Hello');
        static::assertSame('end_turn', $result->getStopReason());

        $sent = $this->sentMessage($transport);
        static::assertSame('session/prompt', $sent['method']);
        static::assertSame('sess_1', self::paramsOf($sent)['sessionId']);
        static::assertSame([['type' => 'text', 'text' => 'Hello']], self::paramsOf($sent)['prompt']);
    }

    public function testSessionPromptAcceptsContentBlocks(): void
    {
        $transport = $this->transportWithResult(['stopReason' => 'end_turn']);
        $client = new Client($transport, 1.0, false);
        $prompt = [
            ['type' => 'text', 'text' => 'Review this file'],
            ['type' => 'resource', 'resource' => ['uri' => 'file:///repo/a.php']],
        ];

        $client->sessionPrompt('sess_1', $prompt);

        $sent = $this->sentMessage($transport);
        static::assertSame($prompt, self::paramsOf($sent)['prompt']);
    }

    public function testStrictProtocolRequiresInitializeBeforeSessionPrompt(): void
    {
        $client = new Client(new FakeTransport(), 1.0);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/prompt before initialize() in strict protocol mode');

        $client->sessionPrompt('sess_1', 'Hello');
    }

    public function testStrictProtocolAcceptsPromptCapabilities(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => [
                'image' => true,
                'audio' => true,
                'embeddedContext' => true,
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => ['stopReason' => 'end_turn'],
        ]);

        $client = new Client($transport, 1.0);
        $client->initialize();
        $client->sessionPrompt('sess_1', [
            ['type' => 'image', 'data' => 'base64-image', 'mimeType' => 'image/png', 'annotations' => []],
            ['type' => 'audio', 'data' => 'base64-audio', 'mimeType' => 'audio/mpeg'],
            ['type' => 'resource', 'resource' => ['uri' => 'file:///repo/a.php', 'text' => 'contents']],
        ]);

        $sent = self::decode($transport->sent[1]);
        static::assertSame('session/prompt', $sent['method']);
    }

    public function testStrictProtocolAcceptsResourceLinkContentBlock(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => ['stopReason' => 'end_turn'],
        ]);

        $client = new Client($transport, 1.0);
        $client->initialize();
        $client->sessionPrompt('sess_1', [
            [
                'type' => 'resource_link',
                'uri' => 'file:///repo/a.php',
                'name' => 'a.php',
                'mimeType' => 'text/x-php',
                'title' => 'Source file',
                'description' => 'File to review',
                'size' => 123,
            ],
        ]);

        $sent = self::decode($transport->sent[1]);
        static::assertSame('session/prompt', $sent['method']);
    }

    public function testStrictProtocolRejectsPromptContentWithoutCapability(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Cannot call session/prompt with image content: agent did not advertise promptCapabilities.image',
        );

        $client->sessionPrompt('sess_1', [
            ['type' => 'image', 'data' => 'base64-image', 'mimeType' => 'image/png'],
        ]);
    }

    public function testStrictProtocolRejectsInvalidPromptBlock(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].text must be a string');

        $client->sessionPrompt('sess_1', [['type' => 'text']]);
    }

    public function testStrictProtocolRejectsNonListPromptArray(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt must be a list of content blocks');

        // @phpstan-ignore argument.type (intentionally passing an associative array to verify rejection)
        /** @var array<int, array<string, mixed>> $invalidPrompt */
        $invalidPrompt = ['type' => 'text', 'text' => 'Hello'];
        $client->sessionPrompt('sess_1', $invalidPrompt);
    }

    public function testStrictProtocolRejectsInvalidResourceLinkOptionalField(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].size must be an integer');

        $client->sessionPrompt('sess_1', [
            ['type' => 'resource_link', 'uri' => 'file:///repo/a.php', 'name' => 'a.php', 'size' => '123'],
        ]);
    }

    public function testStrictProtocolRejectsResourceWithoutUri(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['embeddedContext' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].resource.uri must be a string');

        $client->sessionPrompt('sess_1', [
            ['type' => 'resource', 'resource' => ['text' => 'contents']],
        ]);
    }

    public function testStrictProtocolRejectsResourceWithoutTextOrBlob(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['embeddedContext' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].resource must include text or blob');

        $client->sessionPrompt('sess_1', [
            ['type' => 'resource', 'resource' => ['uri' => 'file:///repo/a.php']],
        ]);
    }

    public function testStrictProtocolRejectsImageWithoutData(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['image' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].data must be a string');

        $client->sessionPrompt('sess_1', [
            ['type' => 'image', 'mimeType' => 'image/png'],
        ]);
    }

    public function testStrictProtocolRejectsAudioWithoutMimeType(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['audio' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].mimeType must be a string');

        $client->sessionPrompt('sess_1', [
            ['type' => 'audio', 'data' => 'base64-audio'],
        ]);
    }

    public function testStrictProtocolRejectsInvalidAnnotations(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['image' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].annotations must be an object');

        $client->sessionPrompt('sess_1', [
            [
                'type' => 'image',
                'data' => 'base64-image',
                'mimeType' => 'image/png',
                'annotations' => ['invalid'],
            ],
        ]);
    }

    public function testStrictProtocolRejectsImageWithInvalidUri(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['image' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].uri must be a string');

        $client->sessionPrompt('sess_1', [
            ['type' => 'image', 'data' => 'base64-image', 'mimeType' => 'image/png', 'uri' => 123],
        ]);
    }

    public function testStrictProtocolRejectsResourceWithBothTextAndBlob(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['embeddedContext' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Invalid session/prompt params: prompt[0].resource cannot include both text and blob',
        );

        $client->sessionPrompt('sess_1', [
            [
                'type' => 'resource',
                'resource' => [
                    'uri' => 'file:///repo/a.php',
                    'text' => 'contents',
                    'blob' => 'base64',
                ],
            ],
        ]);
    }

    public function testSessionCancelSendsNotification(): void
    {
        $transport = new FakeTransport();
        $client = new Client($transport, 1.0, false);

        $client->sessionCancel('sess_1');

        $sent = $this->sentMessage($transport);
        static::assertSame('session/cancel', $sent['method']);
        static::assertArrayNotHasKey('id', $sent);
        static::assertSame(['sessionId' => 'sess_1'], $sent['params']);
    }

    public function testSetConfigOptionCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['configOptions' => []]);
        $client = new Client($transport, 1.0, false);

        static::assertSame(['configOptions' => []], $client->setConfigOption('sess_1', 'mode', 'code'));

        $sent = $this->sentMessage($transport);
        static::assertSame('session/set_config_option', $sent['method']);
        static::assertSame(['sessionId' => 'sess_1', 'configId' => 'mode', 'value' => 'code'], $sent['params']);
    }

    public function testSetModeCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['currentModeId' => 'code']);
        $client = new Client($transport, 1.0, false);

        static::assertSame(['currentModeId' => 'code'], $client->setMode('sess_1', 'code'));

        $sent = $this->sentMessage($transport);
        static::assertSame('session/set_mode', $sent['method']);
        static::assertSame(['sessionId' => 'sess_1', 'modeId' => 'code'], $sent['params']);
    }

    public function testCallReturnsScalarResult(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => 'pong',
        ]);

        $client = new Client($transport, 1.0, false);

        static::assertSame('pong', $client->call('ping'));
    }

    public function testCallSkipsServerNotification(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'method' => 'session/update',
            'params' => ['status' => 'running'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['status' => 'ok'],
        ]);

        $client = new Client($transport, 1.0, false);

        static::assertSame(['status' => 'ok'], $client->call('initialize'));
    }

    public function testCallKeepsUnmatchedResponseForLater(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => ['second' => true],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['first' => true],
        ]);

        $client = new Client($transport, 1.0, false);

        static::assertSame(['first' => true], $client->call('first'));
        static::assertSame(['second' => true], $client->call('second'));
    }

    public function testCallThrowsOnJsonRpcError(): void
    {
        $transport = new FakeTransport();
        $client = new Client($transport, 1.0, false);

        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32_600,
                'message' => 'Invalid Request',
            ],
        ]);

        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Invalid Request');

        $client->call('initialize');
    }

    public function testCallThrowsOnInvalidJsonRpcResponse(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = 'not json';
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid JSON-RPC response');

        $client->call('initialize');
    }

    public function testCallTimesOut(): void
    {
        $transport = new FakeTransport();
        $client = new Client($transport, 0.01, false);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Timeout waiting for response');

        $client->call('initialize');
    }

    public function testNotifyDoesNotWaitForResponse(): void
    {
        $transport = new FakeTransport();
        $client = new Client($transport, 1.0, false);

        $client->notify('agent/cancel', ['taskId' => 'abc']);

        static::assertCount(1, $transport->sent);

        $sent = self::decode($transport->sent[0]);
        static::assertArrayNotHasKey('id', $sent);
        static::assertSame('agent/cancel', $sent['method']);
        static::assertSame(['taskId' => 'abc'], $sent['params']);
    }

    public function testStdioTransportTalksToProcess(): void
    {
        $transport = new StdioTransport([
            'command' => PHP_BINARY,
            'args' => [__DIR__ . '/Fixtures/stdio-agent.php'],
        ]);

        $client = new Client($transport, 1.0, false);

        try {
            static::assertSame(
                ['method' => 'agent/run', 'params' => ['task' => 'test']],
                $client->call('agent/run', ['task' => 'test']),
            );
        } finally {
            $transport->close();
        }
    }

    public function testStdioTransportReadsResponseFromProcessThatExits(): void
    {
        $transport = new StdioTransport([
            'command' => PHP_BINARY,
            'args' => [__DIR__ . '/Fixtures/stdio-once-agent.php'],
        ]);

        $client = new Client($transport, 1.0, false);

        try {
            static::assertSame(['ok' => true], $client->call('initialize'));
        } finally {
            $transport->close();
        }
    }

    public function testStdioTransportIncludesStderrWhenProcessClosesStdout(): void
    {
        $transport = new StdioTransport([
            'command' => PHP_BINARY,
            'args' => [__DIR__ . '/Fixtures/stderr-exit-agent.php'],
        ]);

        $client = new Client($transport, 1.0, false);

        try {
            $this->expectException(TransportException::class);
            $this->expectExceptionMessage('agent failed before responding');

            $client->call('initialize');
        } finally {
            $transport->close();
        }
    }

    public function testNotificationIsDispatchedDuringCall(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'method' => 'session/update',
            'params' => ['status' => 'running'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);

        $received = [];
        $client->onNotification(static function (Notification $notification) use (&$received): void {
            $received[] = [
                'method' => $notification->getMethod(),
                'params' => $notification->getParams(),
            ];
        });

        $result = $client->call('initialize');

        static::assertSame(['ok' => true], $result);
        static::assertSame(
            [
                [
                    'method' => 'session/update',
                    'params' => ['status' => 'running'],
                ],
            ],
            $received,
        );
    }

    public function testMethodSpecificListenerOnlyReceivesMatchingNotifications(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'method' => 'session/update',
            'params' => ['status' => 'running'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'method' => 'agent/log',
            'params' => ['message' => 'hello'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);

        $received = [];
        $client->on('session/update', static function (Notification $notification) use (&$received): void {
            $received[] = $notification->getMethod();
        });

        $client->call('initialize');

        static::assertSame(['session/update'], $received);
    }

    public function testMultipleListenersAllFire(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'method' => 'session/update',
            'params' => ['status' => 'running'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);

        $first = [];
        $second = [];
        $client->onNotification(static function (Notification $notification) use (&$first): void {
            $first[] = $notification->getMethod();
        });
        $client->onNotification(static function (Notification $notification) use (&$second): void {
            $second[] = $notification->getParams()['status'] ?? null;
        });

        $client->call('initialize');

        static::assertSame(['session/update'], $first);
        static::assertSame(['running'], $second);
    }

    public function testNotificationsDoNotInterfereWithResponseMatching(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'method' => 'session/update',
            'params' => ['status' => 'running'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => ['second' => true],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['first' => true],
        ]);

        $client = new Client($transport, 1.0, false);

        static::assertSame(['first' => true], $client->call('first'));
        static::assertSame(['second' => true], $client->call('second'));
    }

    public function testListenerCanBeRemoved(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'method' => 'session/update',
            'params' => ['status' => 'running'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);

        $received = [];
        $listener = static function (Notification $notification) use (&$received): void {
            $received[] = $notification->getMethod();
        };

        $client->onNotification($listener);
        $client->offNotification($listener);

        $client->call('initialize');

        static::assertSame([], $received);
    }

    public function testRegisteredRequestHandlerResponds(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'req-1',
            'method' => 'fs/read_text_file',
            'params' => ['path' => '/repo/a.php'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onRequest('fs/read_text_file', static function (array $params): string {
            $path = $params['path'] ?? '';
            self::assertIsString($path);

            return 'contents of ' . $path;
        });

        $result = $client->call('initialize');

        static::assertSame(['ok' => true], $result);
        static::assertCount(2, $transport->sent);

        $response = self::decode($transport->sent[1]);
        static::assertSame('req-1', $response['id']);
        static::assertArrayNotHasKey('error', $response);
        static::assertSame('contents of /repo/a.php', $response['result']);
    }

    public function testUnknownServerRequestReturnsMethodNotFound(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'req-1',
            'method' => 'fs/unknown',
            'params' => [],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);

        $result = $client->call('initialize');

        static::assertSame(['ok' => true], $result);

        $response = self::decode($transport->sent[1]);
        static::assertSame('req-1', $response['id']);
        static::assertArrayHasKey('error', $response);
        static::assertSame(-32_601, self::errorOf($response)['code']);
    }

    public function testAnyRequestHandlerRespondsToUnknownServerRequest(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'req-1',
            'method' => 'permission/request',
            'params' => ['question' => 'Allow edit?'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onAnyRequest(static fn(string $method, array $params): array => [
            'method' => $method,
            'answer' => ($params['question'] ?? null) === 'Allow edit?' ? 'approved' : 'denied',
        ]);

        static::assertSame(['ok' => true], $client->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame('req-1', $response['id']);
        static::assertArrayNotHasKey('error', $response);
        static::assertSame(['method' => 'permission/request', 'answer' => 'approved'], $response['result']);
    }

    public function testMethodRequestHandlerTakesPrecedenceOverAnyRequestHandler(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'req-1',
            'method' => 'permission/request',
            'params' => ['question' => 'Allow edit?'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onAnyRequest(static fn(): array => ['answer' => 'fallback']);
        $client->onRequest('permission/request', static fn(): array => ['answer' => 'specific']);

        $client->call('initialize');

        $response = self::decode($transport->sent[1]);
        static::assertSame(['answer' => 'specific'], $response['result']);
    }

    public function testAnyRequestHandlerCanBeRemoved(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'req-1',
            'method' => 'permission/request',
            'params' => [],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);

        $handler = static fn(): array => ['answer' => 'fallback'];
        $client->onAnyRequest($handler);
        $client->offAnyRequest($handler);

        $client->call('initialize');

        $response = self::decode($transport->sent[1]);
        static::assertSame(-32_601, self::errorOf($response)['code']);
    }

    public function testHandlerExceptionReturnsInternalError(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'req-1',
            'method' => 'fs/read_text_file',
            'params' => ['path' => '/repo/a.php'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onRequest('fs/read_text_file', static function (): string {
            throw new RuntimeException('disk failure');
        });

        $client->call('initialize');

        $response = self::decode($transport->sent[1]);
        static::assertSame('req-1', $response['id']);
        static::assertArrayHasKey('error', $response);
        $error = self::errorOf($response);
        static::assertSame(-32_603, $error['code']);
        static::assertSame('disk failure', $error['message']);
    }

    public function testServerRequestDoesNotInterfereWithClientResponse(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'req-1',
            'method' => 'fs/read_text_file',
            'params' => ['path' => '/repo/a.php'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => ['second' => true],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['first' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onRequest('fs/read_text_file', static fn(): string => 'file contents');

        static::assertSame(['first' => true], $client->call('first'));
        static::assertSame(['second' => true], $client->call('second'));
    }

    public function testRequestHandlerCanBeRemoved(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'req-1',
            'method' => 'fs/read_text_file',
            'params' => ['path' => '/repo/a.php'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);

        $handler = static fn(): string => 'file contents';
        $client->onRequest('fs/read_text_file', $handler);
        $client->offRequest('fs/read_text_file', $handler);

        $client->call('initialize');

        $response = self::decode($transport->sent[1]);
        static::assertSame(-32_601, self::errorOf($response)['code']);
    }

    public function testRequestPermissionHandlerRespondsWithSelectedOutcome(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'perm-1',
            'method' => 'session/request_permission',
            'params' => [
                'sessionId' => 'sess_1',
                'toolCall' => [
                    'toolCallId' => 'call_1',
                ],
                'options' => [
                    [
                        'optionId' => 'allow-once',
                        'name' => 'Allow once',
                        'kind' => 'allow_once',
                    ],
                ],
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onRequestPermission(static function (RequestPermission $request): RequestPermissionOutcome {
            self::assertSame('sess_1', $request->getSessionId());
            self::assertSame('call_1', $request->getToolCallId());
            self::assertSame('allow-once', $request->getOptions()[0]->getOptionId());

            return RequestPermissionOutcome::selected('allow-once');
        });

        static::assertSame(['ok' => true], $client->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame('perm-1', $response['id']);
        static::assertSame(
            [
                'outcome' => [
                    'outcome' => 'selected',
                    'optionId' => 'allow-once',
                ],
            ],
            $response['result'],
        );
    }

    public function testSessionCancelRespondsToPendingRequestPermissionWithCancelledOutcome(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'perm-1',
            'method' => 'session/request_permission',
            'params' => [
                'sessionId' => 'sess_1',
                'toolCall' => [
                    'toolCallId' => 'call_1',
                ],
                'options' => [
                    [
                        'optionId' => 'allow-once',
                        'name' => 'Allow once',
                        'kind' => 'allow_once',
                    ],
                ],
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['stopReason' => 'cancelled'],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onRequestPermission(static function (RequestPermission $request) use (
            $client,
        ): RequestPermissionOutcome {
            self::assertSame('sess_1', $request->getSessionId());
            $client->sessionCancel('sess_1');

            return RequestPermissionOutcome::selected('allow-once');
        });

        static::assertTrue($client->sessionPrompt('sess_1', 'Cancel this')->isCancelled());

        static::assertCount(3, $transport->sent);

        $cancel = self::decode($transport->sent[1]);
        static::assertSame('session/cancel', $cancel['method']);
        static::assertArrayNotHasKey('id', $cancel);

        $permissionResponse = self::decode($transport->sent[2]);
        static::assertSame('perm-1', $permissionResponse['id']);
        static::assertSame(
            [
                'outcome' => [
                    'outcome' => 'cancelled',
                ],
            ],
            $permissionResponse['result'],
        );
    }

    public function testRequestPermissionHandlerCanBeRemoved(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'perm-1',
            'method' => 'session/request_permission',
            'params' => [
                'sessionId' => 'sess_1',
                'toolCall' => ['toolCallId' => 'call_1'],
                'options' => [],
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $handler =
            static fn(RequestPermission $request): RequestPermissionOutcome => RequestPermissionOutcome::cancelled();

        $client->onRequestPermission($handler);
        $client->offRequestPermission($handler);
        $client->call('initialize');

        $response = self::decode($transport->sent[1]);
        static::assertSame(-32_601, self::errorOf($response)['code']);
    }

    public function testOnReadTextFileRespondsWithResultDto(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'fs-1',
            'method' => 'fs/read_text_file',
            'params' => [
                'sessionId' => 'sess_1',
                'path' => '/repo/a.php',
                'line' => 10,
                'limit' => 50,
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onReadTextFile(static function (ReadTextFileRequest $request): ReadTextFileResult {
            self::assertSame('sess_1', $request->getSessionId());
            self::assertSame('/repo/a.php', $request->getPath());
            self::assertSame(10, $request->getLine());
            self::assertSame(50, $request->getLimit());

            return new ReadTextFileResult('contents');
        });

        static::assertSame(['ok' => true], $client->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame('fs-1', $response['id']);
        static::assertArrayNotHasKey('error', $response);
        static::assertSame(['content' => 'contents'], $response['result']);
    }

    public function testOnReadTextFileWrapsStringResult(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'fs-1',
            'method' => 'fs/read_text_file',
            'params' => [
                'sessionId' => 'sess_1',
                'path' => '/repo/a.php',
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onReadTextFile(static fn(): string => 'plain contents');

        static::assertSame(['ok' => true], $client->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame(['content' => 'plain contents'], $response['result']);
    }

    public function testOnReadTextFileReturnsInvalidParamsForMalformedRequest(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'fs-1',
            'method' => 'fs/read_text_file',
            'params' => ['sessionId' => 'sess_1'],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onReadTextFile(static fn(): string => 'ignored');

        static::assertSame(['ok' => true], $client->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame(-32_602, self::errorOf($response)['code']);
    }

    public function testOnWriteTextFileRespondsWithEmptyResult(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'fs-1',
            'method' => 'fs/write_text_file',
            'params' => [
                'sessionId' => 'sess_1',
                'path' => '/repo/a.php',
                'content' => 'hello',
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onWriteTextFile(static function (WriteTextFileRequest $request): void {
            self::assertSame('sess_1', $request->getSessionId());
            self::assertSame('/repo/a.php', $request->getPath());
            self::assertSame('hello', $request->getContent());
        });

        static::assertSame(['ok' => true], $client->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame('fs-1', $response['id']);
        static::assertArrayNotHasKey('error', $response);
        static::assertSame([], $response['result']);
    }

    public function testOnWriteTextFileRespondsWithResultDto(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'fs-1',
            'method' => 'fs/write_text_file',
            'params' => [
                'sessionId' => 'sess_1',
                'path' => '/repo/a.php',
                'content' => 'hello',
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onWriteTextFile(static fn(): WriteTextFileResult => new WriteTextFileResult());

        static::assertSame(['ok' => true], $client->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame([], $response['result']);
    }

    public function testReadTextFileHandlerTakesPrecedenceOverGenericHandler(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'fs-1',
            'method' => 'fs/read_text_file',
            'params' => [
                'sessionId' => 'sess_1',
                'path' => '/repo/a.php',
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->onRequest('fs/read_text_file', static fn(): string => 'generic');
        $client->onReadTextFile(static fn(): ReadTextFileResult => new ReadTextFileResult('typed'));

        static::assertSame(['ok' => true], $client->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame(['content' => 'typed'], $response['result']);
    }

    public function testReadTextFileHandlerCanBeRemoved(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'fs-1',
            'method' => 'fs/read_text_file',
            'params' => [
                'sessionId' => 'sess_1',
                'path' => '/repo/a.php',
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $handler = static fn(): ReadTextFileResult => new ReadTextFileResult('contents');
        $client->onReadTextFile($handler);
        $client->offReadTextFile($handler);

        static::assertSame(['ok' => true], $client->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame(-32_601, self::errorOf($response)['code']);
    }

    /**
     * @param array<string, mixed> $agentCapabilities
     */
    private function initializedStrictTransport(array $agentCapabilities): FakeTransport
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => 1,
                'agentCapabilities' => $agentCapabilities,
            ],
        ]);

        return $transport;
    }

    private function transportWithResult(mixed $result): FakeTransport
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => $result,
        ]);

        return $transport;
    }

    /**
     * @return array<string, mixed>
     */
    private function sentMessage(FakeTransport $transport): array
    {
        self::assertNotSame([], $transport->sent);

        return self::decode($transport->sent[0]);
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private static function errorOf(array $response): array
    {
        self::assertIsArray($response['error']);

        /** @var array<string, mixed> $error */
        $error = $response['error'];

        return $error;
    }
}
