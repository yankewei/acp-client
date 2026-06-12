<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yankewei\AcpClient\Client;
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

    public function testCallReturnsResult(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['status' => 'ok'],
        ]);

        $client = new Client($transport, 1.0);

        $result = $client->call('initialize');

        self::assertSame(['status' => 'ok'], $result);
        self::assertCount(1, $transport->sent);

        $sent = json_decode($transport->sent[0], true);
        self::assertSame('2.0', $sent['jsonrpc']);
        self::assertSame('initialize', $sent['method']);
        self::assertIsInt($sent['id']);
    }

    public function testInitializeSendsDefaultAcpParams(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => 1,
                'agentCapabilities' => [],
            ],
        ]);

        $client = new Client($transport, 1.0);

        self::assertSame([
            'protocolVersion' => 1,
            'agentCapabilities' => [],
        ], $client->initialize());

        $sent = json_decode($transport->sent[0], true);
        self::assertSame('initialize', $sent['method']);
        self::assertSame(1, $sent['params']['protocolVersion']);
        self::assertFalse($sent['params']['clientCapabilities']['fs']['readTextFile']);
        self::assertFalse($sent['params']['clientCapabilities']['fs']['writeTextFile']);
        self::assertFalse($sent['params']['clientCapabilities']['terminal']);
        self::assertSame('yankewei/acp-client', $sent['params']['clientInfo']['name']);
    }

    public function testInitializeAllowsParamOverrides(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => 1,
                'agentCapabilities' => [],
            ],
        ]);

        $client = new Client($transport, 1.0);
        $client->initialize([
            'clientCapabilities' => [
                'terminal' => true,
            ],
            'clientInfo' => [
                'name' => 'custom-client',
            ],
        ]);

        $sent = json_decode($transport->sent[0], true);
        self::assertTrue($sent['params']['clientCapabilities']['terminal']);
        self::assertSame('custom-client', $sent['params']['clientInfo']['name']);
        self::assertSame('ACP Client for PHP', $sent['params']['clientInfo']['title']);
    }

    public function testAuthenticateCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['ok' => true]);
        $client = new Client($transport, 1.0);

        self::assertSame(['ok' => true], $client->authenticate('login'));

        $sent = $this->sentMessage($transport);
        self::assertSame('authenticate', $sent['method']);
        self::assertSame(['methodId' => 'login'], $sent['params']);
    }

    public function testLogoutCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult([]);
        $client = new Client($transport, 1.0);

        self::assertSame([], $client->logout());

        $sent = $this->sentMessage($transport);
        self::assertSame('logout', $sent['method']);
        self::assertSame([], $sent['params']);
    }

    public function testSessionNewCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['sessionId' => 'sess_1']);
        $client = new Client($transport, 1.0);

        self::assertSame(
            ['sessionId' => 'sess_1'],
            $client->sessionNew('/repo', [['name' => 'fs']], ['/shared']),
        );

        $sent = $this->sentMessage($transport);
        self::assertSame('session/new', $sent['method']);
        self::assertSame('/repo', $sent['params']['cwd']);
        self::assertSame([['name' => 'fs']], $sent['params']['mcpServers']);
        self::assertSame(['/shared'], $sent['params']['additionalDirectories']);
    }

    public function testSessionLoadCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(null);
        $client = new Client($transport, 1.0);

        self::assertNull($client->sessionLoad('sess_1', '/repo'));

        $sent = $this->sentMessage($transport);
        self::assertSame('session/load', $sent['method']);
        self::assertSame('sess_1', $sent['params']['sessionId']);
        self::assertSame('/repo', $sent['params']['cwd']);
        self::assertSame([], $sent['params']['mcpServers']);
        self::assertArrayNotHasKey('additionalDirectories', $sent['params']);
    }

    public function testSessionResumeCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['ready' => true]);
        $client = new Client($transport, 1.0);

        self::assertSame(['ready' => true], $client->sessionResume('sess_1', '/repo'));

        $sent = $this->sentMessage($transport);
        self::assertSame('session/resume', $sent['method']);
        self::assertSame('sess_1', $sent['params']['sessionId']);
        self::assertSame('/repo', $sent['params']['cwd']);
    }

    public function testSessionCloseCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult([]);
        $client = new Client($transport, 1.0);

        self::assertSame([], $client->sessionClose('sess_1'));

        $sent = $this->sentMessage($transport);
        self::assertSame('session/close', $sent['method']);
        self::assertSame(['sessionId' => 'sess_1'], $sent['params']);
    }

    public function testSessionListCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['sessions' => [], 'nextCursor' => 'next']);
        $client = new Client($transport, 1.0);

        self::assertSame(
            ['sessions' => [], 'nextCursor' => 'next'],
            $client->sessionList('/repo', 'cursor'),
        );

        $sent = $this->sentMessage($transport);
        self::assertSame('session/list', $sent['method']);
        self::assertSame(['cwd' => '/repo', 'cursor' => 'cursor'], $sent['params']);
    }

    public function testSessionDeleteCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult([]);
        $client = new Client($transport, 1.0);

        self::assertSame([], $client->sessionDelete('sess_1'));

        $sent = $this->sentMessage($transport);
        self::assertSame('session/delete', $sent['method']);
        self::assertSame(['sessionId' => 'sess_1'], $sent['params']);
    }

    public function testSessionPromptAcceptsText(): void
    {
        $transport = $this->transportWithResult(['stopReason' => 'end_turn']);
        $client = new Client($transport, 1.0);

        self::assertSame(['stopReason' => 'end_turn'], $client->sessionPrompt('sess_1', 'Hello'));

        $sent = $this->sentMessage($transport);
        self::assertSame('session/prompt', $sent['method']);
        self::assertSame('sess_1', $sent['params']['sessionId']);
        self::assertSame([['type' => 'text', 'text' => 'Hello']], $sent['params']['prompt']);
    }

    public function testSessionPromptAcceptsContentBlocks(): void
    {
        $transport = $this->transportWithResult(['stopReason' => 'end_turn']);
        $client = new Client($transport, 1.0);
        $prompt = [
            ['type' => 'text', 'text' => 'Review this file'],
            ['type' => 'resource', 'resource' => ['uri' => 'file:///repo/a.php']],
        ];

        $client->sessionPrompt('sess_1', $prompt);

        $sent = $this->sentMessage($transport);
        self::assertSame($prompt, $sent['params']['prompt']);
    }

    public function testSessionCancelSendsNotification(): void
    {
        $transport = new FakeTransport();
        $client = new Client($transport, 1.0);

        $client->sessionCancel('sess_1');

        $sent = $this->sentMessage($transport);
        self::assertSame('session/cancel', $sent['method']);
        self::assertArrayNotHasKey('id', $sent);
        self::assertSame(['sessionId' => 'sess_1'], $sent['params']);
    }

    public function testSetConfigOptionCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['configOptions' => []]);
        $client = new Client($transport, 1.0);

        self::assertSame(
            ['configOptions' => []],
            $client->setConfigOption('sess_1', 'mode', 'code'),
        );

        $sent = $this->sentMessage($transport);
        self::assertSame('session/set_config_option', $sent['method']);
        self::assertSame(
            ['sessionId' => 'sess_1', 'configId' => 'mode', 'value' => 'code'],
            $sent['params'],
        );
    }

    public function testSetModeCallsAcpMethod(): void
    {
        $transport = $this->transportWithResult(['currentModeId' => 'code']);
        $client = new Client($transport, 1.0);

        self::assertSame(['currentModeId' => 'code'], $client->setMode('sess_1', 'code'));

        $sent = $this->sentMessage($transport);
        self::assertSame('session/set_mode', $sent['method']);
        self::assertSame(['sessionId' => 'sess_1', 'modeId' => 'code'], $sent['params']);
    }

    public function testCallReturnsScalarResult(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => 'pong',
        ]);

        $client = new Client($transport, 1.0);

        self::assertSame('pong', $client->call('ping'));
    }

    public function testCallSkipsServerNotification(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'session/update',
            'params' => ['status' => 'running'],
        ]);
        $transport->responses[] = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['status' => 'ok'],
        ]);

        $client = new Client($transport, 1.0);

        self::assertSame(['status' => 'ok'], $client->call('initialize'));
    }

    public function testCallKeepsUnmatchedResponseForLater(): void
    {
        $transport = new FakeTransport();
        $transport->responses[] = json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => ['second' => true],
        ]);
        $transport->responses[] = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['first' => true],
        ]);

        $client = new Client($transport, 1.0);

        self::assertSame(['first' => true], $client->call('first'));
        self::assertSame(['second' => true], $client->call('second'));
    }

    public function testCallThrowsOnJsonRpcError(): void
    {
        $transport = new FakeTransport();
        $client = new Client($transport, 1.0);

        $transport->responses[] = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request',
            ],
        ]);

        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Invalid Request');

        $client->call('initialize');
    }

    public function testCallTimesOut(): void
    {
        $transport = new FakeTransport();
        $client = new Client($transport, 0.01);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Timeout waiting for response');

        $client->call('initialize');
    }

    public function testNotifyDoesNotWaitForResponse(): void
    {
        $transport = new FakeTransport();
        $client = new Client($transport, 1.0);

        $client->notify('agent/cancel', ['taskId' => 'abc']);

        self::assertCount(1, $transport->sent);

        $sent = json_decode($transport->sent[0], true);
        self::assertArrayNotHasKey('id', $sent);
        self::assertSame('agent/cancel', $sent['method']);
        self::assertSame(['taskId' => 'abc'], $sent['params']);
    }

    public function testStdioTransportTalksToProcess(): void
    {
        $transport = new StdioTransport([
            'command' => PHP_BINARY,
            'args' => [__DIR__ . '/Fixtures/stdio-agent.php'],
        ]);

        $client = new Client($transport, 1.0);

        try {
            self::assertSame(
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

        $client = new Client($transport, 1.0);

        try {
            self::assertSame(['ok' => true], $client->call('initialize'));
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

        $client = new Client($transport, 1.0);

        try {
            $this->expectException(TransportException::class);
            $this->expectExceptionMessage('agent failed before responding');

            $client->call('initialize');
        } finally {
            $transport->close();
        }
    }

    private function transportWithResult(mixed $result): FakeTransport
    {
        $transport = new FakeTransport();
        $transport->responses[] = json_encode([
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

        return json_decode($transport->sent[0], true);
    }
}
