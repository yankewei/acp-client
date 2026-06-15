<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Exception\JsonRpcException;

final class JsonRpcExceptionTest extends TestCase
{
    public function testIsAuthenticationRequired(): void
    {
        static::assertTrue(
            (new JsonRpcException(
                JsonRpcException::AUTHENTICATION_REQUIRED,
                'Authentication required',
            ))->isAuthenticationRequired(),
        );

        static::assertFalse((new JsonRpcException(-32_600, 'Invalid request'))->isAuthenticationRequired());
    }
}
