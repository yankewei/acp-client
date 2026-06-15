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

        static::assertSame('session/update', $notification->getMethod());
        static::assertSame(['status' => 'running'], $notification->getParams());
    }

    public function testIsMethodMatches(): void
    {
        $notification = new Notification('session/update', []);

        static::assertTrue($notification->is('session/update'));
        static::assertFalse($notification->is('agent/update'));
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

        static::assertNotNull($update);
        static::assertSame('sess_1', $update->getSessionId());
        static::assertTrue($update->hasTitle());
        static::assertNull($update->getTitle());
        static::assertTrue($update->hasUpdatedAt());
        static::assertSame('2026-06-13T00:00:00Z', $update->getUpdatedAt());
        static::assertTrue($update->hasMeta());
        static::assertSame(['priority' => 'high'], $update->getMeta());
    }

    public function testSessionInfoUpdateReturnsNullForOtherNotifications(): void
    {
        static::assertNull(SessionInfoUpdateMapper::fromNotification(new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => ['sessionUpdate' => 'agent_message_chunk'],
        ])));

        static::assertNull(SessionInfoUpdateMapper::fromNotification(new Notification('agent/update', [])));
    }
}
