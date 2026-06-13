<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Event\Notification;
use Yankewei\AcpClient\Event\SessionInfoUpdateMapper;

final class NotificationTest extends TestCase
{
    public function testHoldsMethodAndParams(): void
    {
        $notification = new Notification('session/update', ['status' => 'running']);

        self::assertSame('session/update', $notification->getMethod());
        self::assertSame(['status' => 'running'], $notification->getParams());
    }

    public function testIsMethodMatches(): void
    {
        $notification = new Notification('session/update', []);

        self::assertTrue($notification->is('session/update'));
        self::assertFalse($notification->is('agent/update'));
    }

    public function testParsesSessionInfoUpdate(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => [
                'sessionUpdate' => 'session_info_update',
                'title' => null,
                'updatedAt' => '2026-06-13T00:00:00Z',
                '_meta' => ['priority' => 'high'],
            ],
        ]);

        $update = SessionInfoUpdateMapper::fromNotification($notification);

        self::assertNotNull($update);
        self::assertSame('sess_1', $update->getSessionId());
        self::assertTrue($update->hasTitle());
        self::assertNull($update->getTitle());
        self::assertTrue($update->hasUpdatedAt());
        self::assertSame('2026-06-13T00:00:00Z', $update->getUpdatedAt());
        self::assertTrue($update->hasMeta());
        self::assertSame(['priority' => 'high'], $update->getMeta());
    }

    public function testSessionInfoUpdateReturnsNullForOtherNotifications(): void
    {
        self::assertNull(SessionInfoUpdateMapper::fromNotification(new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => ['sessionUpdate' => 'agent_message_chunk'],
        ])));

        self::assertNull(SessionInfoUpdateMapper::fromNotification(new Notification('agent/update', [])));
    }
}
