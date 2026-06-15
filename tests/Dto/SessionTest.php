<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\Session;

final class SessionTest extends TestCase
{
    public function testFromArrayParsesSessionId(): void
    {
        $session = Session::fromArray(['sessionId' => 'sess_1']);

        static::assertSame('sess_1', $session->getSessionId());
        static::assertSame([], $session->getConfigOptions());
    }

    public function testFromArrayParsesConfigOptions(): void
    {
        $session = Session::fromArray([
            'sessionId' => 'sess_1',
            'configOptions' => [
                ['id' => 'mode', 'currentValue' => 'code'],
            ],
        ]);

        static::assertSame([['id' => 'mode', 'currentValue' => 'code']], $session->getConfigOptions());
    }

    public function testFromArrayAllowsMissingFields(): void
    {
        $session = Session::fromArray(['ready' => true]);

        static::assertNull($session->getSessionId());
        static::assertSame([], $session->getConfigOptions());
    }
}
