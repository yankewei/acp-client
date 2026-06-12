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

        self::assertSame(1, $response->getId());
        self::assertFalse($response->hasError());
        self::assertSame(['ok' => true], $response->getResult());
    }

    public function testParseErrorResponse(): void
    {
        $response = Response::fromJson('{"jsonrpc":"2.0","id":2,"error":{"code":-32600,"message":"Invalid Request"}}');

        self::assertSame(2, $response->getId());
        self::assertTrue($response->hasError());
        self::assertSame(-32600, $response->getError()->getCode());
        self::assertSame('Invalid Request', $response->getError()->getMessage());
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
}
