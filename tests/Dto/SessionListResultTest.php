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
                [
                    'sessionId' => 'sess_1',
                    'cwd' => '/repo',
                    'additionalDirectories' => ['/shared'],
                    'title' => 'Build list support',
                    'updatedAt' => '2026-06-13T00:00:00Z',
                    '_meta' => ['messageCount' => 3],
                ],
            ],
            'nextCursor' => 'cursor',
        ]);

        self::assertSame(
            [
                [
                    'sessionId' => 'sess_1',
                    'cwd' => '/repo',
                    'additionalDirectories' => ['/shared'],
                    'title' => 'Build list support',
                    'updatedAt' => '2026-06-13T00:00:00Z',
                    '_meta' => ['messageCount' => 3],
                ],
            ],
            $result->getSessions(),
        );

        $sessionInfos = $result->getSessionInfos();
        self::assertCount(1, $sessionInfos);
        self::assertSame('sess_1', $sessionInfos[0]->getSessionId());
        self::assertSame('/repo', $sessionInfos[0]->getCwd());
        self::assertSame(['/shared'], $sessionInfos[0]->getAdditionalDirectories());
        self::assertSame('Build list support', $sessionInfos[0]->getTitle());
        self::assertSame('2026-06-13T00:00:00Z', $sessionInfos[0]->getUpdatedAt());
        self::assertSame(['messageCount' => 3], $sessionInfos[0]->getMeta());
        self::assertSame('cursor', $result->getNextCursor());
    }

    public function testFromArrayAllowsEmptySessions(): void
    {
        $result = SessionListResult::fromArray(['sessions' => []]);

        self::assertSame([], $result->getSessions());
        self::assertNull($result->getNextCursor());
    }

    public function testFromArrayRequiresSessions(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/list result: sessions is required');

        SessionListResult::fromArray([]);
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

    public function testFromArrayRejectsRelativeSessionCwd(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session info: cwd must be an absolute path');

        SessionListResult::fromArray([
            'sessions' => [
                [
                    'sessionId' => 'sess_1',
                    'cwd' => 'repo',
                ],
            ],
        ]);
    }

    public function testFromArrayRejectsRelativeAdditionalDirectory(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session info: additionalDirectories entries must be absolute paths');

        SessionListResult::fromArray([
            'sessions' => [
                [
                    'sessionId' => 'sess_1',
                    'cwd' => '/repo',
                    'additionalDirectories' => ['shared'],
                ],
            ],
        ]);
    }
}
