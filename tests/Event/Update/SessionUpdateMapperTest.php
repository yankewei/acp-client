<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event\Update;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Event\Notification;
use Yankewei\AcpClient\Event\SessionInfoUpdate;
use Yankewei\AcpClient\Event\Update\AgentMessageChunkUpdate;
use Yankewei\AcpClient\Event\Update\PlanUpdate;
use Yankewei\AcpClient\Event\Update\SessionUpdate;
use Yankewei\AcpClient\Event\Update\SessionUpdateMapper;
use Yankewei\AcpClient\Event\Update\ToolCallStatusUpdate;
use Yankewei\AcpClient\Event\Update\ToolCallUpdate;
use Yankewei\AcpClient\Event\Update\UsageUpdate;
use Yankewei\AcpClient\Exception\AcpException;

final class SessionUpdateMapperTest extends TestCase
{
    public function testReturnsNullForNonSessionUpdateNotification(): void
    {
        $notification = new Notification('session/created', [
            'sessionId' => 'sess_1',
            'update' => ['sessionUpdate' => 'session_info_update'],
        ]);

        static::assertNull(SessionUpdateMapper::fromNotification($notification));
    }

    public function testReturnsNullForUnknownSessionUpdateType(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => ['sessionUpdate' => 'unknown_update'],
        ]);

        static::assertNull(SessionUpdateMapper::fromNotification($notification));
    }

    public function testReturnsNullWhenUpdateIsMissing(): void
    {
        $notification = new Notification('session/update', ['sessionId' => 'sess_1']);

        static::assertNull(SessionUpdateMapper::fromNotification($notification));
    }

    public function testReturnsNullWhenUpdateIsList(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => ['agent_message_chunk'],
        ]);

        static::assertNull(SessionUpdateMapper::fromNotification($notification));
    }

    public function testReturnsNullWhenSessionUpdateIsMissing(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => ['content' => ['type' => 'text', 'text' => 'Hi']],
        ]);

        static::assertNull(SessionUpdateMapper::fromNotification($notification));
    }

    public function testReturnsNullWhenSessionUpdateIsNotString(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => ['sessionUpdate' => 123],
        ]);

        static::assertNull(SessionUpdateMapper::fromNotification($notification));
    }

    public function testDispatchesSessionInfoUpdate(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => ['sessionUpdate' => 'session_info_update'],
        ]);

        $update = SessionUpdateMapper::fromNotification($notification);

        static::assertInstanceOf(SessionUpdate::class, $update);
        static::assertInstanceOf(SessionInfoUpdate::class, $update);
        static::assertSame('sess_1', $update->getSessionId());
        static::assertSame('session_info_update', $update->getUpdateType());
    }

    public function testDispatchesAgentMessageChunkUpdate(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => [
                'sessionUpdate' => 'agent_message_chunk',
                'messageId' => 'msg_1',
                'content' => ['type' => 'text', 'text' => 'Hello'],
            ],
        ]);

        $update = SessionUpdateMapper::fromNotification($notification);

        static::assertInstanceOf(AgentMessageChunkUpdate::class, $update);
        static::assertSame('sess_1', $update->getSessionId());
        static::assertSame('agent_message_chunk', $update->getUpdateType());
    }

    public function testDispatchesToolCallUpdate(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => [
                'sessionUpdate' => 'tool_call',
                'toolCallId' => 'tc_1',
                'title' => 'Read file',
            ],
        ]);

        $update = SessionUpdateMapper::fromNotification($notification);

        static::assertInstanceOf(ToolCallUpdate::class, $update);
        static::assertSame('sess_1', $update->getSessionId());
        static::assertSame('tool_call', $update->getUpdateType());
    }

    public function testDispatchesToolCallStatusUpdate(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => [
                'sessionUpdate' => 'tool_call_update',
                'toolCallId' => 'tc_1',
                'status' => 'completed',
            ],
        ]);

        $update = SessionUpdateMapper::fromNotification($notification);

        static::assertInstanceOf(ToolCallStatusUpdate::class, $update);
        static::assertSame('sess_1', $update->getSessionId());
        static::assertSame('tool_call_update', $update->getUpdateType());
    }

    public function testDispatchesUsageUpdate(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => [
                'sessionUpdate' => 'usage_update',
                'used' => 100,
                'size' => 200,
            ],
        ]);

        $update = SessionUpdateMapper::fromNotification($notification);

        static::assertInstanceOf(UsageUpdate::class, $update);
        static::assertSame('sess_1', $update->getSessionId());
        static::assertSame('usage_update', $update->getUpdateType());
    }

    public function testDispatchesPlanUpdate(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => [
                'sessionUpdate' => 'plan',
                'entries' => [
                    ['content' => 'First step'],
                ],
            ],
        ]);

        $update = SessionUpdateMapper::fromNotification($notification);

        static::assertInstanceOf(PlanUpdate::class, $update);
        static::assertSame('sess_1', $update->getSessionId());
        static::assertSame('plan', $update->getUpdateType());
    }

    public function testRejectsMissingSessionId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/update notification: sessionId must be a string');

        $notification = new Notification('session/update', [
            'update' => ['sessionUpdate' => 'session_info_update'],
        ]);

        SessionUpdateMapper::fromNotification($notification);
    }
}
