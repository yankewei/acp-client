<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Exception\JsonRpcException;

final class JsonRpcExceptionTest extends TestCase
{
    public function testIsAuthenticationRequired(): void
    {
        self::assertTrue(
            (new JsonRpcException(
                JsonRpcException::AUTHENTICATION_REQUIRED,
                'Authentication required',
            ))->isAuthenticationRequired(),
        );

        self::assertFalse((new JsonRpcException(-32600, 'Invalid request'))->isAuthenticationRequired());
    }
}
