<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\SessionListResult;
use Yankewei\AcpClient\Exception\AcpException;

final class SessionListResultTest extends TestCase
{
    public function testFromArrayParsesSessionsAndCursor(): void
    {
        $result = SessionListResult::fromArray([
            'sessions' => [
                ['sessionId' => 'sess_1'],
            ],
            'nextCursor' => 'cursor',
        ]);

        self::assertSame([['sessionId' => 'sess_1']], $result->getSessions());
        self::assertSame('cursor', $result->getNextCursor());
    }

    public function testFromArrayDefaults(): void
    {
        $result = SessionListResult::fromArray([]);

        self::assertSame([], $result->getSessions());
        self::assertNull($result->getNextCursor());
    }

    public function testFromArrayRejectsNonListSessions(): void
    {
        $this->expectException(AcpException::class);

        SessionListResult::fromArray(['sessions' => ['sessionId' => 'sess_1']]);
    }

    public function testFromArrayRejectsInvalidSessionItem(): void
    {
        $this->expectException(AcpException::class);

        SessionListResult::fromArray(['sessions' => [['sessionId' => 'sess_1'], ['list']]]);
    }
}
