<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\JsonRpc;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\JsonRpc\Response;

final class ResponseTest extends TestCase
{
    public function testParseSuccessfulResponse(): void
    {
        $response = Response::fromJson('{"jsonrpc":"2.0","id":1,"result":{"ok":true}}');

        static::assertSame(1, $response->getId());
        static::assertFalse($response->hasError());
        static::assertSame(['ok' => true], $response->getResult());
    }

    public function testParseErrorResponse(): void
    {
        $response = Response::fromJson('{"jsonrpc":"2.0","id":2,"error":{"code":-32600,"message":"Invalid Request"}}');

        static::assertSame(2, $response->getId());
        static::assertTrue($response->hasError());

        $error = $response->getError();
        static::assertNotNull($error);
        static::assertSame(-32_600, $error->getCode());
        static::assertSame('Invalid Request', $error->getMessage());
    }

    public function testParseInvalidJsonThrows(): void
    {
        $this->expectException(AcpException::class);
        Response::fromJson('not json');
    }

    public function testParseMissingIdThrows(): void
    {
        $this->expectException(AcpException::class);
        Response::fromJson('{"jsonrpc":"2.0","result":{}}');
    }

    public function testParseMissingJsonRpcVersionThrows(): void
    {
        $this->expectException(AcpException::class);
        Response::fromJson('{"id":1,"result":{}}');
    }

    public function testParseInvalidIdThrows(): void
    {
        $this->expectException(AcpException::class);
        Response::fromJson('{"jsonrpc":"2.0","id":{},"result":{}}');
    }

    public function testParseResultAndErrorThrows(): void
    {
        $this->expectException(AcpException::class);
        Response::fromJson('{"jsonrpc":"2.0","id":1,"result":{},"error":{"code":-32600,"message":"Invalid Request"}}');
    }

    public function testParseMissingResultAndErrorThrows(): void
    {
        $this->expectException(AcpException::class);
        Response::fromJson('{"jsonrpc":"2.0","id":1}');
    }

    public function testParseInvalidErrorCodeThrows(): void
    {
        $this->expectException(AcpException::class);
        Response::fromJson('{"jsonrpc":"2.0","id":1,"error":{"code":"-32600","message":"Invalid Request"}}');
    }

    public function testParseInvalidErrorMessageThrows(): void
    {
        $this->expectException(AcpException::class);
        Response::fromJson('{"jsonrpc":"2.0","id":1,"error":{"code":-32600,"message":false}}');
    }
}
