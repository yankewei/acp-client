<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Event\SessionInfoUpdate;
use Yankewei\AcpClient\Event\Update\SessionUpdate;

final class SessionInfoUpdateTest extends TestCase
{
    public function testImplementsSessionUpdate(): void
    {
        $update = SessionInfoUpdate::fromUpdate('sess_1', ['sessionUpdate' => 'session_info_update']);

        self::assertInstanceOf(SessionUpdate::class, $update);
        self::assertSame('sess_1', $update->getSessionId());
        self::assertSame('session_info_update', $update->getUpdateType());
    }
}
