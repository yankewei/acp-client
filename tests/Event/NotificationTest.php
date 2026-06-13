<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Event\Notification;

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
}
