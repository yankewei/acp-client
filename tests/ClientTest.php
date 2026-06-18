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
use Yankewei\AcpClient\Dto\Terminal\TerminalCreateRequest;
use Yankewei\AcpClient\Dto\Terminal\TerminalCreateResult;
use Yankewei\AcpClient\Dto\Terminal\TerminalKillRequest;
use Yankewei\AcpClient\Dto\Terminal\TerminalOutputRequest;
use Yankewei\AcpClient\Dto\Terminal\TerminalOutputResult;
use Yankewei\AcpClient\Dto\Terminal\TerminalReleaseRequest;
use Yankewei\AcpClient\Dto\Terminal\TerminalWaitForExitRequest;
use Yankewei\AcpClient\Dto\Terminal\TerminalWaitForExitResult;
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
        return self::objectArray($sent['params']);
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return array<string, mixed>
     */
    private static function getArray(array $array, string $key): array
    {
        return self::objectArray($array[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function objectArray(mixed $value): array
    {
        self::assertIsArray($value);
        self::assertFalse(array_is_list($value));

        /** @var array<string, mixed> $value */
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

        $result = $client->rpc()->call('initialize');

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

        $result = $client->acp()->initialize();
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
        $client->acp()->initialize([
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

    public function testInitializeAdvertisesReadTextFileCapabilityWhenHandlerRegistered(): void
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
        $client
            ->requests()
            ->onReadTextFile(
                static fn(ReadTextFileRequest $_request): ReadTextFileResult => new ReadTextFileResult(''),
            );
        $client->acp()->initialize();

        $sent = self::decode($transport->sent[0]);
        $clientCapabilities = self::getArray(self::paramsOf($sent), 'clientCapabilities');
        $fs = self::getArray($clientCapabilities, 'fs');

        static::assertTrue($fs['readTextFile']);
        static::assertFalse($fs['writeTextFile']);
    }

    public function testInitializeAdvertisesWriteTextFileCapabilityWhenHandlerRegistered(): void
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
        $client
            ->requests()
            ->onWriteTextFile(
                static fn(WriteTextFileRequest $_request): WriteTextFileResult => new WriteTextFileResult(),
            );
        $client->acp()->initialize();

        $sent = self::decode($transport->sent[0]);
        $clientCapabilities = self::getArray(self::paramsOf($sent), 'clientCapabilities');
        $fs = self::getArray($clientCapabilities, 'fs');

        static::assertFalse($fs['readTextFile']);
        static::assertTrue($fs['writeTextFile']);
    }

    public function testInitializeDoesNotAdvertiseFsCapabilitiesAfterHandlerRemoved(): void
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
        $handler = static fn(ReadTextFileRequest $_request): ReadTextFileResult => new ReadTextFileResult('');
        $client->requests()->onReadTextFile($handler);
        $client->requests()->offReadTextFile($handler);
        $client->acp()->initialize();

        $sent = self::decode($transport->sent[0]);
        $clientCapabilities = self::getArray(self::paramsOf($sent), 'clientCapabilities');
        $fs = self::getArray($clientCapabilities, 'fs');

        static::assertFalse($fs['readTextFile']);
        static::assertFalse($fs['writeTextFile']);
    }

    public function testInitializeAdvertisesTerminalCapabilityWhenHandlerRegistered(): void
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
        $client
            ->requests()
            ->onTerminalCreate(
                static fn(TerminalCreateRequest $_request): TerminalCreateResult => TerminalCreateResult::fromTerminalId(
                    'term_1',
                ),
            );
        $client->acp()->initialize();

        $sent = self::decode($transport->sent[0]);
        $clientCapabilities = self::getArray(self::paramsOf($sent), 'clientCapabilities');

        static::assertTrue($clientCapabilities['terminal']);
    }

    public function testInitializeDoesNotAdvertiseTerminalCapabilityAfterHandlerRemoved(): void
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
        $handler =
            static fn(TerminalCreateRequest $_request): TerminalCreateResult => TerminalCreateResult::fromTerminalId(
                'term_1',
            );
        $client->requests()->onTerminalCreate($handler);
        $client->requests()->offTerminalCreate($handler);
        $client->acp()->initialize();

        $sent = self::decode($transport->sent[0]);
        $clientCapabilities = self::getArray(self::paramsOf($sent), 'clientCapabilities');

        static::assertFalse($clientCapabilities['terminal']);
    }

    public function testInitializeAdvertisesFsCapabilityWhenGenericHandlerRegisteredForKnownMethod(): void
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
        $client->requests()->onRequest('fs/read_text_file', static fn(array $_params): string => 'contents');
        $client->acp()->initialize();

        $sent = self::decode($transport->sent[0]);
        $clientCapabilities = self::getArray(self::paramsOf($sent), 'clientCapabilities');
        $fs = self::getArray($clientCapabilities, 'fs');

        static::assertTrue($fs['readTextFile']);
        static::assertFalse($fs['writeTextFile']);
    }

    public function testInitializeAdvertisesTerminalCapabilityWhenGenericHandlerRegisteredForKnownMethod(): void
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
        $client->requests()->onRequest('terminal/kill', static fn(array $_params): array => []);
        $client->acp()->initialize();

        $sent = self::decode($transport->sent[0]);
        $clientCapabilities = self::getArray(self::paramsOf($sent), 'clientCapabilities');

        static::assertTrue($clientCapabilities['terminal']);
    }

    public function testGenericRequestHandlerOverwritesPreviousHandlerForSameMethod(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'req-1',
            'method' => 'custom/method',
            'params' => [],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client->requests()->onRequest('custom/method', static fn(array $_params): array => ['answer' => 'first']);
        $client->requests()->onRequest('custom/method', static fn(array $_params): array => ['answer' => 'second']);

        $client->rpc()->call('initialize');

        $response = self::decode($transport->sent[1]);
        static::assertSame(['answer' => 'second'], $response['result']);
    }

    public function testAuthenticateCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['ok' => true]);
        $client = new Client($transport, 1.0, false);

        static::assertSame(['ok' => true], $client->acp()->authenticate('login'));
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
        $client->acp()->initialize();

        static::assertSame([], $client->acp()->authenticate('login'));

        $sent = self::decode($transport->sent[1]);
        static::assertSame('authenticate', $sent['method']);
        static::assertSame(['methodId' => 'login'], $sent['params']);
    }

    public function testStrictProtocolRejectsUnadvertisedAuthMethod(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call authenticate: agent did not advertise auth method login');

        $client->acp()->authenticate('login');
    }

    public function testStrictProtocolRequiresInitializeBeforeAuthenticate(): void
    {
        $client = new Client(new FakeTransport(), 1.0, true);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call authenticate before initialize() in strict protocol mode');

        $client->acp()->authenticate('login');
    }

    public function testLogoutCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult([]);
        $client = new Client($transport, 1.0, false);

        static::assertSame([], $client->acp()->logout());

        $sent = $this->sentMessage($transport);
        static::assertSame('logout', $sent['method']);
        static::assertSame([], $sent['params']);
    }

    public function testSessionNewCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['sessionId' => 'sess_1']);
        $client = new Client($transport, 1.0, false);

        $session = $client->acp()->sessionNew('/repo', [['name' => 'fs']], ['/shared']);
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

        static::assertNull($client->acp()->sessionLoad('sess_1', '/repo'));

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

        $session = $client->acp()->sessionResume('sess_1', '/repo');
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

        $session = $client->acp()->sessionClose('sess_1');
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

        $client->acp()->sessionNew('/repo');
    }

    public function testStrictProtocolIsEnabledByDefault(): void
    {
        $client = new Client(new FakeTransport(), 1.0);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/new before initialize() in strict protocol mode');

        $client->acp()->sessionNew('/repo');
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
        $client->acp()->initialize();
        $session = $client->acp()->sessionNew(
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
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/new params: cwd must be an absolute path');

        $client->acp()->sessionNew('repo');
    }

    public function testStrictProtocolRejectsAdditionalDirectoriesWithoutCapability(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Cannot call session/new with additionalDirectories: agent did not advertise sessionCapabilities.additionalDirectories',
        );

        $client->acp()->sessionNew('/repo', [], ['/shared']);
    }

    public function testStrictProtocolRejectsLoadResumeAndCloseWithoutCapabilities(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/load: agent did not advertise loadSession');

        $client->acp()->sessionLoad('sess_1', '/repo');
    }

    public function testStrictProtocolRejectsResumeWithoutCapability(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/resume: agent did not advertise sessionCapabilities.resume');

        $client->acp()->sessionResume('sess_1', '/repo');
    }

    public function testStrictProtocolRejectsCloseWithoutCapability(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/close: agent did not advertise sessionCapabilities.close');

        $client->acp()->sessionClose('sess_1');
    }

    public function testStrictProtocolRejectsInvalidMcpServerShape(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0, true);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/new params: mcpServers[0].command must be an absolute path');

        $client->acp()->sessionNew('/repo', [
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
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Invalid session/new params: mcpServers[0].env must be a list of name/value objects',
        );

        $client->acp()->sessionNew('/repo', [
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
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Cannot call session/new with HTTP MCP server: agent did not advertise mcpCapabilities.http',
        );

        $client->acp()->sessionNew('/repo', [
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

        $result = $client->acp()->sessionList('/repo', 'cursor');
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

        $client->acp()->sessionList();
    }

    public function testStrictProtocolRejectsSessionListWithoutCapability(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/list: agent did not advertise sessionCapabilities.list');

        $client->acp()->sessionList();
    }

    public function testStrictProtocolRejectsRelativeSessionListCwd(): void
    {
        $transport = $this->initializedStrictTransport([
            'sessionCapabilities' => ['list' => []],
        ]);
        $client = new Client($transport, 1.0);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/list params: cwd must be an absolute path');

        $client->acp()->sessionList('repo');
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
        $client->acp()->initialize();
        $result = $client->acp()->sessionList('/repo', 'cursor');

        static::assertSame('sess_1', $result->getSessionInfos()[0]->getSessionId());

        $sent = self::decode($transport->sent[1]);
        static::assertSame('session/list', $sent['method']);
        static::assertSame(['cwd' => '/repo', 'cursor' => 'cursor'], $sent['params']);
    }

    public function testSessionDeleteCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult([]);
        $client = new Client($transport, 1.0, false);

        static::assertSame([], $client->acp()->sessionDelete('sess_1'));

        $sent = $this->sentMessage($transport);
        static::assertSame('session/delete', $sent['method']);
        static::assertSame(['sessionId' => 'sess_1'], $sent['params']);
    }

    public function testStrictProtocolRequiresInitializeBeforeSessionDelete(): void
    {
        $client = new Client(new FakeTransport(), 1.0);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/delete before initialize() in strict protocol mode');

        $client->acp()->sessionDelete('sess_1');
    }

    public function testStrictProtocolRejectsSessionDeleteWithoutCapability(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/delete: agent did not advertise sessionCapabilities.delete');

        $client->acp()->sessionDelete('sess_1');
    }

    public function testSessionPromptAcceptsText(): void
    {
        $transport = $this->transportWithResult(['stopReason' => 'end_turn']);
        $client = new Client($transport, 1.0, false);

        $result = $client->acp()->sessionPrompt('sess_1', 'Hello');
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

        $client->acp()->sessionPrompt('sess_1', $prompt);

        $sent = $this->sentMessage($transport);
        static::assertSame($prompt, self::paramsOf($sent)['prompt']);
    }

    public function testSessionPromptIncludesMeta(): void
    {
        $transport = $this->transportWithResult(['stopReason' => 'end_turn']);
        $client = new Client($transport, 1.0, false);

        $client->acp()->sessionPrompt('sess_1', 'Hello', meta: [
            'traceparent' => '00-80e1afed08e019fc1110464cfa66635c-7a085853722dc6d2-01',
            'zed.dev/debugMode' => true,
        ]);

        $sent = $this->sentMessage($transport);
        static::assertSame('session/prompt', $sent['method']);
        static::assertSame(
            [
                'traceparent' => '00-80e1afed08e019fc1110464cfa66635c-7a085853722dc6d2-01',
                'zed.dev/debugMode' => true,
            ],
            self::paramsOf($sent)['_meta'],
        );
    }

    public function testSessionPromptRejectsListMeta(): void
    {
        $client = new Client(new FakeTransport(), 1.0, false);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: _meta must be an object');

        $client->acp()->sessionPrompt('sess_1', 'Hello', meta: ['traceparent']);
    }

    public function testSessionSlashCommandUsesSessionPrompt(): void
    {
        $transport = $this->transportWithResult(['stopReason' => 'end_turn']);
        $client = new Client($transport, 1.0, false);

        $result = $client->acp()->sessionSlashCommand('sess_1', '/web', 'agent client protocol');
        static::assertSame('end_turn', $result->getStopReason());

        $sent = $this->sentMessage($transport);
        static::assertSame('session/prompt', $sent['method']);
        static::assertSame('sess_1', self::paramsOf($sent)['sessionId']);
        static::assertSame(
            [['type' => 'text', 'text' => '/web agent client protocol']],
            self::paramsOf($sent)['prompt'],
        );
    }

    public function testSessionSlashCommandRejectsEmptyCommand(): void
    {
        $client = new Client(new FakeTransport(), 1.0, false);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid slash command: command must not be empty');

        $client->acp()->sessionSlashCommand('sess_1', '/');
    }

    public function testStrictProtocolRequiresInitializeBeforeSessionPrompt(): void
    {
        $client = new Client(new FakeTransport(), 1.0);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Cannot call session/prompt before initialize() in strict protocol mode');

        $client->acp()->sessionPrompt('sess_1', 'Hello');
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
        $client->acp()->initialize();
        $client->acp()->sessionPrompt('sess_1', [
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
        $client->acp()->initialize();
        $client->acp()->sessionPrompt('sess_1', [
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
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Cannot call session/prompt with image content: agent did not advertise promptCapabilities.image',
        );

        $client->acp()->sessionPrompt('sess_1', [
            ['type' => 'image', 'data' => 'base64-image', 'mimeType' => 'image/png'],
        ]);
    }

    public function testStrictProtocolRejectsInvalidPromptBlock(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].text must be a string');

        $client->acp()->sessionPrompt('sess_1', [['type' => 'text']]);
    }

    public function testStrictProtocolRejectsNonListPromptArray(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt must be a list of content blocks');

        /** @var array<int, array<string, mixed>> $prompt */
        $prompt = ['type' => 'text', 'text' => 'Hello'];
        $client->acp()->sessionPrompt('sess_1', $prompt);
    }

    public function testStrictProtocolRejectsInvalidResourceLinkOptionalField(): void
    {
        $transport = $this->initializedStrictTransport([]);
        $client = new Client($transport, 1.0);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].size must be an integer');

        $client->acp()->sessionPrompt('sess_1', [
            ['type' => 'resource_link', 'uri' => 'file:///repo/a.php', 'name' => 'a.php', 'size' => '123'],
        ]);
    }

    public function testStrictProtocolRejectsResourceWithoutUri(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['embeddedContext' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].resource.uri must be a string');

        $client->acp()->sessionPrompt('sess_1', [
            ['type' => 'resource', 'resource' => ['text' => 'contents']],
        ]);
    }

    public function testStrictProtocolRejectsResourceWithoutTextOrBlob(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['embeddedContext' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].resource must include text or blob');

        $client->acp()->sessionPrompt('sess_1', [
            ['type' => 'resource', 'resource' => ['uri' => 'file:///repo/a.php']],
        ]);
    }

    public function testStrictProtocolRejectsImageWithoutData(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['image' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].data must be a string');

        $client->acp()->sessionPrompt('sess_1', [
            ['type' => 'image', 'mimeType' => 'image/png'],
        ]);
    }

    public function testStrictProtocolRejectsAudioWithoutMimeType(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['audio' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].mimeType must be a string');

        $client->acp()->sessionPrompt('sess_1', [
            ['type' => 'audio', 'data' => 'base64-audio'],
        ]);
    }

    public function testStrictProtocolRejectsInvalidAnnotations(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['image' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].annotations must be an object');

        $client->acp()->sessionPrompt('sess_1', [
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
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt params: prompt[0].uri must be a string');

        $client->acp()->sessionPrompt('sess_1', [
            ['type' => 'image', 'data' => 'base64-image', 'mimeType' => 'image/png', 'uri' => 123],
        ]);
    }

    public function testStrictProtocolRejectsResourceWithBothTextAndBlob(): void
    {
        $transport = $this->initializedStrictTransport([
            'promptCapabilities' => ['embeddedContext' => true],
        ]);
        $client = new Client($transport, 1.0);
        $client->acp()->initialize();

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Invalid session/prompt params: prompt[0].resource cannot include both text and blob',
        );

        $client->acp()->sessionPrompt('sess_1', [
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

        $client->acp()->sessionCancel('sess_1');

        $sent = $this->sentMessage($transport);
        static::assertSame('session/cancel', $sent['method']);
        static::assertArrayNotHasKey('id', $sent);
        static::assertSame(['sessionId' => 'sess_1'], $sent['params']);
    }

    public function testSetConfigOptionCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult([
            'configOptions' => [
                [
                    'id' => 'mode',
                    'name' => 'Session Mode',
                    'type' => 'select',
                    'currentValue' => 'code',
                    'options' => [
                        ['value' => 'code', 'name' => 'Code'],
                    ],
                ],
            ],
        ]);
        $client = new Client($transport, 1.0, false);

        $result = $client->acp()->setConfigOption('sess_1', 'mode', 'code');

        static::assertSame('mode', $result->getConfigOptionObjects()[0]->getId());
        static::assertSame('code', $result->getConfigOptionObjects()[0]->getCurrentValue());

        $sent = $this->sentMessage($transport);
        static::assertSame('session/set_config_option', $sent['method']);
        static::assertSame(['sessionId' => 'sess_1', 'configId' => 'mode', 'value' => 'code'], $sent['params']);
    }

    public function testSetModeUsesModeConfigOption(): void
    {
        $transport = $this->transportWithResult([
            'configOptions' => [
                [
                    'id' => 'mode',
                    'name' => 'Session Mode',
                    'type' => 'select',
                    'currentValue' => 'code',
                    'options' => [
                        ['value' => 'code', 'name' => 'Code'],
                    ],
                ],
            ],
        ]);
        $client = new Client($transport, 1.0, false);

        $result = $client->acp()->setMode('sess_1', 'code');

        static::assertSame('mode', $result->getConfigOptionObjects()[0]->getId());
        static::assertSame('code', $result->getConfigOptionObjects()[0]->getCurrentValue());

        $sent = $this->sentMessage($transport);
        static::assertSame('session/set_config_option', $sent['method']);
        static::assertSame(['sessionId' => 'sess_1', 'configId' => 'mode', 'value' => 'code'], $sent['params']);
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

        static::assertSame('pong', $client->rpc()->call('ping'));
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

        static::assertSame(['status' => 'ok'], $client->rpc()->call('initialize'));
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

        static::assertSame(['first' => true], $client->rpc()->call('first'));
        static::assertSame(['second' => true], $client->rpc()->call('second'));
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

        $client->rpc()->call('initialize');
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

        $client->rpc()->call('initialize');
    }

    public function testCallTimesOut(): void
    {
        $transport = new FakeTransport();
        $client = new Client($transport, 0.01, false);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Timeout waiting for response');

        $client->rpc()->call('initialize');
    }

    public function testNotifyDoesNotWaitForResponse(): void
    {
        $transport = new FakeTransport();
        $client = new Client($transport, 1.0, false);

        $client->rpc()->notify('agent/cancel', ['taskId' => 'abc']);

        static::assertCount(1, $transport->sent);

        $sent = self::decode($transport->sent[0]);
        static::assertArrayNotHasKey('id', $sent);
        static::assertSame('agent/cancel', $sent['method']);
        static::assertSame(['taskId' => 'abc'], $sent['params']);
    }

    public function testExtensionCallRequiresUnderscoreMethod(): void
    {
        $client = new Client(new FakeTransport(), 1.0, false);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid extension method: method must start with "_"');

        $client->rpc()->callExtension('zed.dev/workspace/buffers');
    }

    public function testExtensionCallSendsCustomRequest(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['buffers' => []],
        ]);

        $client = new Client($transport, 1.0, false);

        static::assertSame(
            ['buffers' => []],
            $client->rpc()->callExtension('_zed.dev/workspace/buffers', ['language' => 'rust']),
        );

        $sent = $this->sentMessage($transport);
        static::assertSame('_zed.dev/workspace/buffers', $sent['method']);
        static::assertSame(['language' => 'rust'], $sent['params']);
    }

    public function testExtensionNotifyRequiresUnderscoreMethod(): void
    {
        $client = new Client(new FakeTransport(), 1.0, false);

        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid extension method: method must start with "_"');

        $client->rpc()->notifyExtension('zed.dev/file_opened');
    }

    public function testExtensionNotifySendsCustomNotification(): void
    {
        $transport = new FakeTransport();
        $client = new Client($transport, 1.0, false);

        $client->rpc()->notifyExtension('_zed.dev/file_opened', ['path' => '/repo/src/main.rs']);

        $sent = $this->sentMessage($transport);
        static::assertArrayNotHasKey('id', $sent);
        static::assertSame('_zed.dev/file_opened', $sent['method']);
        static::assertSame(['path' => '/repo/src/main.rs'], $sent['params']);
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
                $client->rpc()->call('agent/run', ['task' => 'test']),
            );
        } finally {
            $transport->close();
        }
    }

    public function testStdioTransportRejectsEmbeddedNewlines(): void
    {
        $transport = new StdioTransport([
            'command' => PHP_BINARY,
            'args' => [__DIR__ . '/Fixtures/stdio-agent.php'],
        ]);

        try {
            $transport->open();

            $this->expectException(TransportException::class);
            $this->expectExceptionMessage(
                'Invalid stdio message: JSON-RPC messages must not contain embedded newlines',
            );

            $transport->send("{\"jsonrpc\":\"2.0\"}\n{\"jsonrpc\":\"2.0\"}");
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
            static::assertSame(['ok' => true], $client->rpc()->call('initialize'));
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

            $client->rpc()->call('initialize');
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
        $client
            ->notifications()
            ->onNotification(static function (Notification $notification) use (&$received): void {
                $received[] = [
                    'method' => $notification->getMethod(),
                    'params' => $notification->getParams(),
                ];
            });

        $result = $client->rpc()->call('initialize');

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
        $client->notifications()->on('session/update', static function (Notification $notification) use (
            &$received,
        ): void {
            $received[] = $notification->getMethod();
        });

        $client->rpc()->call('initialize');

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
        $client
            ->notifications()
            ->onNotification(static function (Notification $notification) use (&$first): void {
                $first[] = $notification->getMethod();
            });
        $client
            ->notifications()
            ->onNotification(static function (Notification $notification) use (&$second): void {
                $second[] = $notification->getParams()['status'] ?? null;
            });

        $client->rpc()->call('initialize');

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

        static::assertSame(['first' => true], $client->rpc()->call('first'));
        static::assertSame(['second' => true], $client->rpc()->call('second'));
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

        $client->notifications()->onNotification($listener);
        $client->notifications()->offNotification($listener);

        $client->rpc()->call('initialize');

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
        $client->requests()->onRequest('fs/read_text_file', static function (array $params): string {
            $path = $params['path'] ?? '';
            self::assertIsString($path);

            return 'contents of ' . $path;
        });

        $result = $client->rpc()->call('initialize');

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

        $result = $client->rpc()->call('initialize');

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
        $client
            ->requests()
            ->onAnyRequest(static fn(string $method, array $params): array => [
                'method' => $method,
                'answer' => ($params['question'] ?? null) === 'Allow edit?' ? 'approved' : 'denied',
            ]);

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

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
        $client
            ->requests()
            ->onAnyRequest(static fn(string $_method, array $_params): array => ['answer' => 'fallback']);
        $client->requests()->onRequest('permission/request', static fn(array $_params): array => [
            'answer' => 'specific',
        ]);

        $client->rpc()->call('initialize');

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

        $handler = static fn(string $_method, array $_params): array => ['answer' => 'fallback'];
        $client->requests()->onAnyRequest($handler);
        $client->requests()->offAnyRequest($handler);

        $client->rpc()->call('initialize');

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
        $client->requests()->onRequest('fs/read_text_file', static function (): string {
            throw new RuntimeException('disk failure');
        });

        $client->rpc()->call('initialize');

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
        $client->requests()->onRequest('fs/read_text_file', static fn(): string => 'file contents');

        static::assertSame(['first' => true], $client->rpc()->call('first'));
        static::assertSame(['second' => true], $client->rpc()->call('second'));
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
        $client->requests()->onRequest('fs/read_text_file', $handler);
        $client->requests()->offRequest('fs/read_text_file', $handler);

        $client->rpc()->call('initialize');

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
        $client
            ->requests()
            ->onRequestPermission(static function (RequestPermission $request): RequestPermissionOutcome {
                self::assertSame('sess_1', $request->getSessionId());
                self::assertSame('call_1', $request->getToolCallId());
                self::assertSame('allow-once', $request->getOptions()[0]->getOptionId());

                return RequestPermissionOutcome::selected('allow-once');
            });

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

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
        $client
            ->requests()
            ->onRequestPermission(static function (RequestPermission $request) use ($client): RequestPermissionOutcome {
                self::assertSame('sess_1', $request->getSessionId());
                $client->acp()->sessionCancel('sess_1');

                return RequestPermissionOutcome::selected('allow-once');
            });

        static::assertTrue($client->acp()->sessionPrompt('sess_1', 'Cancel this')->isCancelled());

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
            static fn(RequestPermission $_request): RequestPermissionOutcome => RequestPermissionOutcome::cancelled();

        $client->requests()->onRequestPermission($handler);
        $client->requests()->offRequestPermission($handler);
        $client->rpc()->call('initialize');

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
        $client
            ->requests()
            ->onReadTextFile(static function (ReadTextFileRequest $request): ReadTextFileResult {
                self::assertSame('sess_1', $request->getSessionId());
                self::assertSame('/repo/a.php', $request->getPath());
                self::assertSame(10, $request->getLine());
                self::assertSame(50, $request->getLimit());

                return new ReadTextFileResult('contents');
            });

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

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
        $client->requests()->onReadTextFile(static fn(): string => 'plain contents');

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

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
        $client->requests()->onReadTextFile(static fn(): string => 'ignored');

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

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
        $client
            ->requests()
            ->onWriteTextFile(static function (WriteTextFileRequest $request): void {
                self::assertSame('sess_1', $request->getSessionId());
                self::assertSame('/repo/a.php', $request->getPath());
                self::assertSame('hello', $request->getContent());
            });

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame('fs-1', $response['id']);
        static::assertArrayNotHasKey('error', $response);
        static::assertArrayHasKey('result', $response);
        static::assertNull($response['result']);
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
        $client->requests()->onWriteTextFile(static fn(): WriteTextFileResult => new WriteTextFileResult());

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertArrayHasKey('result', $response);
        static::assertNull($response['result']);
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
        $client->requests()->onRequest('fs/read_text_file', static fn(): string => 'generic');
        $client->requests()->onReadTextFile(static fn(): ReadTextFileResult => new ReadTextFileResult('typed'));

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

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
        $client->requests()->onReadTextFile($handler);
        $client->requests()->offReadTextFile($handler);

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame(-32_601, self::errorOf($response)['code']);
    }

    public function testOnTerminalCreateRespondsWithResultDto(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'term-1',
            'method' => 'terminal/create',
            'params' => [
                'sessionId' => 'sess_1',
                'command' => 'npm',
                'args' => ['test'],
                'env' => [['name' => 'NODE_ENV', 'value' => 'test']],
                'cwd' => '/home/user/project',
                'outputByteLimit' => 1_048_576,
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client
            ->requests()
            ->onTerminalCreate(static function (TerminalCreateRequest $request): TerminalCreateResult {
                self::assertSame('sess_1', $request->getSessionId());
                self::assertSame('npm', $request->getCommand());
                self::assertSame(['test'], $request->getArgs());
                self::assertSame([['name' => 'NODE_ENV', 'value' => 'test']], $request->getEnv());
                self::assertSame('/home/user/project', $request->getCwd());
                self::assertSame(1_048_576, $request->getOutputByteLimit());

                return TerminalCreateResult::fromTerminalId('term_xyz789');
            });

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame('term-1', $response['id']);
        static::assertArrayNotHasKey('error', $response);
        static::assertSame(['terminalId' => 'term_xyz789'], $response['result']);
    }

    public function testOnTerminalOutputRespondsWithResultDto(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'term-1',
            'method' => 'terminal/output',
            'params' => [
                'sessionId' => 'sess_1',
                'terminalId' => 'term_xyz789',
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client
            ->requests()
            ->onTerminalOutput(static function (TerminalOutputRequest $request): TerminalOutputResult {
                self::assertSame('sess_1', $request->getSessionId());
                self::assertSame('term_xyz789', $request->getTerminalId());

                return new TerminalOutputResult('done', false);
            });

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame('term-1', $response['id']);
        static::assertArrayNotHasKey('error', $response);
        static::assertSame(['output' => 'done', 'truncated' => false], $response['result']);
    }

    public function testOnTerminalWaitForExitRespondsWithResultDto(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'term-1',
            'method' => 'terminal/wait_for_exit',
            'params' => [
                'sessionId' => 'sess_1',
                'terminalId' => 'term_xyz789',
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client
            ->requests()
            ->onTerminalWaitForExit(static fn(TerminalWaitForExitRequest $_request): TerminalWaitForExitResult => new TerminalWaitForExitResult(
                0,
                null,
            ));

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame('term-1', $response['id']);
        static::assertArrayNotHasKey('error', $response);
        static::assertSame(['exitCode' => 0, 'signal' => null], $response['result']);
    }

    public function testOnTerminalKillRespondsWithEmptyResult(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'term-1',
            'method' => 'terminal/kill',
            'params' => [
                'sessionId' => 'sess_1',
                'terminalId' => 'term_xyz789',
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client
            ->requests()
            ->onTerminalKill(static function (TerminalKillRequest $request): void {
                self::assertSame('sess_1', $request->getSessionId());
                self::assertSame('term_xyz789', $request->getTerminalId());
            });

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame('term-1', $response['id']);
        static::assertArrayNotHasKey('error', $response);
        static::assertArrayHasKey('result', $response);
        static::assertNull($response['result']);
    }

    public function testOnTerminalReleaseRespondsWithEmptyResult(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 'term-1',
            'method' => 'terminal/release',
            'params' => [
                'sessionId' => 'sess_1',
                'terminalId' => 'term_xyz789',
            ],
        ]);
        $transport->responses[] = self::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['ok' => true],
        ]);

        $client = new Client($transport, 1.0, false);
        $client
            ->requests()
            ->onTerminalRelease(static function (TerminalReleaseRequest $request): void {
                self::assertSame('sess_1', $request->getSessionId());
                self::assertSame('term_xyz789', $request->getTerminalId());
            });

        static::assertSame(['ok' => true], $client->rpc()->call('initialize'));

        $response = self::decode($transport->sent[1]);
        static::assertSame('term-1', $response['id']);
        static::assertArrayNotHasKey('error', $response);
        static::assertArrayHasKey('result', $response);
        static::assertNull($response['result']);
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
        return self::objectArray($response['error']);
    }
}
