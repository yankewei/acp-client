<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event\Update;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\ContentBlockInterface;
use Yankewei\AcpClient\Dto\ContentBlock\TextContentBlock;
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
                ['type' => 'text', 'text' => 'chunk 1'],
                ['type' => 'text', 'text' => 'chunk 2'],
            ],
        ]);

        self::assertInstanceOf(SessionUpdate::class, $update);
        self::assertSame('sess_1', $update->getSessionId());
        self::assertSame('tool_call_update', $update->getUpdateType());
        self::assertSame('call_1', $update->getToolCallId());
        self::assertSame('in_progress', $update->getStatus());
        self::assertCount(2, $update->getContentBlocks());
        self::assertInstanceOf(TextContentBlock::class, $update->getContentBlocks()[0]);
        self::assertInstanceOf(TextContentBlock::class, $update->getContentBlocks()[1]);
        self::assertInstanceOf(TextContentBlock::class, $update->getContentBlocks()[0]);
        self::assertSame(
            [
                ['type' => 'text', 'text' => 'chunk 1'],
                ['type' => 'text', 'text' => 'chunk 2'],
            ],
            $update->getContent(),
        );
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
        $this->expectExceptionMessage('Invalid tool_call_update update: status must be one of pending, in_progress, completed, failed');

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
