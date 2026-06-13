<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event\Update;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\ContentBlockInterface;
use Yankewei\AcpClient\Dto\ContentBlock\TextContentBlock;
use Yankewei\AcpClient\Event\Update\SessionUpdate;
use Yankewei\AcpClient\Event\Update\ToolCallUpdate;
use Yankewei\AcpClient\Exception\AcpException;

final class ToolCallUpdateTest extends TestCase
{
    public function testParsesAllFields(): void
    {
        $update = ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Read file',
            'kind' => 'read',
            'status' => 'completed',
            'content' => [
                ['type' => 'text', 'text' => 'Line 1'],
                ['type' => 'text', 'text' => 'Line 2'],
            ],
            'locations' => [['path' => '/tmp/foo.txt']],
            'rawInput' => ['path' => '/tmp/foo.txt'],
            'rawOutput' => ['content' => 'hello'],
        ]);

        self::assertInstanceOf(SessionUpdate::class, $update);
        self::assertSame('sess_1', $update->getSessionId());
        self::assertSame('tool_call', $update->getUpdateType());
        self::assertSame('call_1', $update->getToolCallId());
        self::assertSame('Read file', $update->getTitle());
        self::assertSame('read', $update->getKind());
        self::assertSame('completed', $update->getStatus());
        self::assertCount(2, $update->getContentBlocks());
        self::assertInstanceOf(TextContentBlock::class, $update->getContentBlocks()[0]);
        self::assertInstanceOf(TextContentBlock::class, $update->getContentBlocks()[1]);
        self::assertSame(
            [
                ['type' => 'text', 'text' => 'Line 1'],
                ['type' => 'text', 'text' => 'Line 2'],
            ],
            $update->getContent(),
        );
        self::assertSame([['path' => '/tmp/foo.txt']], $update->getLocations());
        self::assertSame(['path' => '/tmp/foo.txt'], $update->getRawInput());
        self::assertSame(['content' => 'hello'], $update->getRawOutput());
    }

    public function testDefaultsForMissingKindAndStatus(): void
    {
        $update = ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Unknown action',
        ]);

        self::assertSame('other', $update->getKind());
        self::assertSame('pending', $update->getStatus());
        self::assertSame([], $update->getContent());
        self::assertSame([], $update->getLocations());
        self::assertNull($update->getRawInput());
        self::assertNull($update->getRawOutput());
    }

    public function testRejectsWrongDiscriminator(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call update: sessionUpdate must be tool_call');

        ToolCallUpdate::fromUpdate('sess_1', ['sessionUpdate' => 'agent_message_chunk']);
    }

    public function testRejectsMissingToolCallId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call update: toolCallId must be a string');

        ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'title' => 'Read file',
        ]);
    }

    public function testRejectsInvalidToolCallIdType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call update: toolCallId must be a string');

        ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 123,
            'title' => 'Read file',
        ]);
    }

    public function testRejectsMissingTitle(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call update: title must be a string');

        ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
        ]);
    }

    public function testRejectsInvalidTitleType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call update: title must be a string');

        ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => null,
        ]);
    }

    public function testRejectsInvalidKind(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call update: kind must be one of read, edit, delete, move, search, execute, think, fetch, other');

        ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Read file',
            'kind' => 'fly',
        ]);
    }

    public function testRejectsInvalidStatus(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call update: status must be one of pending, in_progress, completed, failed');

        ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Read file',
            'status' => 'done',
        ]);
    }

    public function testRejectsAssociativeContent(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call update: content must be a list');

        ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Read file',
            'content' => ['foo' => 'bar'],
        ]);
    }

    public function testRejectsAssociativeLocations(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call update: locations must be a list');

        ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Read file',
            'locations' => ['path' => '/tmp/foo.txt'],
        ]);
    }

    public function testRejectsListRawInput(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call update: rawInput must be an object');

        ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Read file',
            'rawInput' => ['/tmp/foo.txt'],
        ]);
    }

    public function testRejectsListRawOutput(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool_call update: rawOutput must be an object');

        ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Read file',
            'rawOutput' => ['hello'],
        ]);
    }
}
