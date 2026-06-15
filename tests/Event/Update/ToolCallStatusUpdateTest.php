<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event\Update;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ToolCallContent\ContentToolCallContent;
use Yankewei\AcpClient\Dto\ToolCallContent\DiffToolCallContent;
use Yankewei\AcpClient\Dto\ToolCallContent\TerminalToolCallContent;
use Yankewei\AcpClient\Event\Update\SessionUpdate;
use Yankewei\AcpClient\Event\Update\ToolCallStatusUpdate;
use Yankewei\AcpClient\Exception\AcpException;

final class ToolCallStatusUpdateTest extends TestCase
{
    public function testParsesAllFields(): void
    {
        $update = ToolCallStatusUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call_update',
            'toolCallId' => 'call_1',
            'status' => 'in_progress',
            'content' => [
                ['type' => 'content', 'content' => ['type' => 'text', 'text' => 'chunk 1']],
                ['type' => 'content', 'content' => ['type' => 'text', 'text' => 'chunk 2']],
            ],
        ]);

        self::assertInstanceOf(SessionUpdate::class, $update);
        self::assertSame('sess_1', $update->getSessionId());
        self::assertSame('tool_call_update', $update->getUpdateType());
        self::assertSame('call_1', $update->getToolCallId());
        self::assertSame('in_progress', $update->getStatus());
        self::assertCount(2, $update->getContentItems());
        self::assertInstanceOf(ContentToolCallContent::class, $update->getContentItems()[0]);
        self::assertInstanceOf(ContentToolCallContent::class, $update->getContentItems()[1]);
        self::assertSame(
            [
                ['type' => 'content', 'content' => ['type' => 'text', 'text' => 'chunk 1']],
                ['type' => 'content', 'content' => ['type' => 'text', 'text' => 'chunk 2']],
            ],
            $update->getContent(),
        );
    }

    public function testParsesDiffContent(): void
    {
        $update = ToolCallStatusUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call_update',
            'toolCallId' => 'call_1',
            'status' => 'completed',
            'content' => [
                [
                    'type' => 'diff',
                    'path' => '/src/main.py',
                    'newText' => 'print("hello")',
                ],
            ],
        ]);

        self::assertCount(1, $update->getContentItems());
        $item = $update->getContentItems()[0];
        self::assertInstanceOf(DiffToolCallContent::class, $item);
        self::assertSame('/src/main.py', $item->getPath());
        self::assertSame('print("hello")', $item->getNewText());
        self::assertNull($item->getOldText());
    }

    public function testParsesTerminalContent(): void
    {
        $update = ToolCallStatusUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call_update',
            'toolCallId' => 'call_1',
            'status' => 'in_progress',
            'content' => [
                ['type' => 'terminal', 'terminalId' => 'term_abc'],
            ],
        ]);

        self::assertCount(1, $update->getContentItems());
        $item = $update->getContentItems()[0];
        self::assertInstanceOf(TerminalToolCallContent::class, $item);
        self::assertSame('term_abc', $item->getTerminalId());
    }

    public function testDefaultsForMissingStatusAndContent(): void
    {
        $update = ToolCallStatusUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call_update',
            'toolCallId' => 'call_1',
        ]);

        self::assertNull($update->getStatus());
        self::assertSame([], $update->getContent());
    }

    public function testRejectsWrongDiscriminator(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call_update update: sessionUpdate must be tool_call_update');

        ToolCallStatusUpdate::fromUpdate('sess_1', ['sessionUpdate' => 'agent_message_chunk']);
    }

    public function testRejectsInvalidStatus(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Invalid tool_call_update update: status must be one of pending, in_progress, completed, failed',
        );

        ToolCallStatusUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call_update',
            'toolCallId' => 'call_1',
            'status' => 'done',
        ]);
    }

    public function testRejectsAssociativeContent(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call_update update: content must be a list');

        ToolCallStatusUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call_update',
            'toolCallId' => 'call_1',
            'content' => ['foo' => 'bar'],
        ]);
    }

    public function testRejectsMissingToolCallId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call_update update: toolCallId must be a string');

        ToolCallStatusUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call_update',
        ]);
    }

    public function testRejectsInvalidToolCallIdType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call_update update: toolCallId must be a string');

        ToolCallStatusUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call_update',
            'toolCallId' => 123,
        ]);
    }
}
