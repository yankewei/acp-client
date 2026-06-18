<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Exception\TransportException;
use Yankewei\AcpClient\Transport\StreamableHttpResponse;
use Yankewei\AcpClient\Transport\StreamableHttpTransport;

final class StreamableHttpTransportTest extends TestCase
{
    public function testSendsJsonRpcOverHttpAndQueuesJsonResponse(): void
    {
        $http = new FakeStreamableHttpClient(
            new StreamableHttpResponse(200, ['Content-Type' => 'application/json'], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['ok' => true],
            ], JSON_THROW_ON_ERROR)),
        );

        $transport = new StreamableHttpTransport([
            'url' => 'https://agent.example/acp',
            'headers' => ['Authorization' => 'Bearer token'],
            'timeout' => 5.0,
        ], $http);
        $transport->open();
        $transport->send(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
        ], JSON_THROW_ON_ERROR));

        static::assertSame('https://agent.example/acp', $http->requests[0]['url']);
        static::assertSame(5.0, $http->requests[0]['timeout']);
        static::assertSame('Bearer token', $http->requests[0]['headers']['Authorization']);
        static::assertSame('application/json', $http->requests[0]['headers']['Content-Type']);
        static::assertSame('application/json, text/event-stream', $http->requests[0]['headers']['Accept']);

        $request = json_decode($http->requests[0]['body'], associative: true, flags: JSON_THROW_ON_ERROR);
        static::assertIsArray($request);
        static::assertSame('initialize', $request['method']);

        $response = $transport->receive();
        static::assertNotNull($response);
        static::assertSame(
            ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['ok' => true]],
            json_decode($response, associative: true, flags: JSON_THROW_ON_ERROR),
        );
        static::assertNull($transport->receive());
    }

    public function testQueuesSseDataMessages(): void
    {
        $http = new FakeStreamableHttpClient(
            new StreamableHttpResponse(
                200,
                ['Content-Type' => 'text/event-stream'],
                "event: message\n"
                . 'data: {"jsonrpc":"2.0","id":1,"result":{"first":true}}'
                . "\n\n"
                . "event: message\n"
                . 'data: {"jsonrpc":"2.0","id":2,"result":{"second":true}}'
                . "\n\n",
            ),
        );

        $transport = new StreamableHttpTransport(['url' => 'https://agent.example/acp'], $http);
        $transport->open();
        $transport->send('{"jsonrpc":"2.0","id":1,"method":"initialize"}');

        static::assertSame(
            ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['first' => true]],
            json_decode((string) $transport->receive(), associative: true, flags: JSON_THROW_ON_ERROR),
        );
        static::assertSame(
            ['jsonrpc' => '2.0', 'id' => 2, 'result' => ['second' => true]],
            json_decode((string) $transport->receive(), associative: true, flags: JSON_THROW_ON_ERROR),
        );
        static::assertNull($transport->receive());
    }

    public function testNotificationWithEmptyAcceptedResponseQueuesNoMessages(): void
    {
        $http = new FakeStreamableHttpClient(new StreamableHttpResponse(202, [], ''));

        $transport = new StreamableHttpTransport(['url' => 'https://agent.example/acp'], $http);
        $transport->open();
        $transport->send('{"jsonrpc":"2.0","method":"notify"}');

        static::assertNull($transport->receive());
    }

    public function testReceiveReturnsNullImmediatelyWhenQueueDrainedRegardlessOfTimeout(): void
    {
        $http = new FakeStreamableHttpClient(
            new StreamableHttpResponse(200, ['Content-Type' => 'application/json'], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['ok' => true],
            ], JSON_THROW_ON_ERROR)),
        );

        $transport = new StreamableHttpTransport(['url' => 'https://agent.example/acp'], $http);
        $transport->open();
        $transport->send('{"jsonrpc":"2.0","id":1,"method":"initialize"}');

        static::assertNotNull($transport->receive());

        $start = microtime(true);
        static::assertNull($transport->receive(5.0));
        static::assertLessThan(0.5, microtime(true) - $start);
    }

    public function testThrowsOnHttpErrorResponse(): void
    {
        $http = new FakeStreamableHttpClient(new StreamableHttpResponse(500, [], 'server failed'));

        $transport = new StreamableHttpTransport(['url' => 'https://agent.example/acp'], $http);
        $transport->open();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Streamable HTTP request failed with status 500');

        $transport->send('{"jsonrpc":"2.0","id":1,"method":"initialize"}');
    }

    public function testRejectsInvalidUrl(): void
    {
        $transport = new StreamableHttpTransport(['url' => 'not a url']);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Invalid streamable HTTP transport URL');

        $transport->open();
    }
}
