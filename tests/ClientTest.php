<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yankewei\AcpClient\Client;
use Yankewei\AcpClient\Exception\JsonRpcException;
use Yankewei\AcpClient\Exception\TransportException;
use Yankewei\AcpClient\JsonRpc\Request;

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
}
