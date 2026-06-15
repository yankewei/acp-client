<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event\Update;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\TextContentBlock;
use Yankewei\AcpClient\Dto\ToolCallContent\ContentToolCallContent;
use Yankewei\AcpClient\Dto\ToolCallContent\DiffToolCallContent;
use Yankewei\AcpClient\Dto\ToolCallContent\TerminalToolCallContent;
use Yankewei\AcpClient\Dto\ToolCallLocation;
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
                ['type' => 'content', 'content' => ['type' => 'text', 'text' => 'Line 1']],
                ['type' => 'content', 'content' => ['type' => 'text', 'text' => 'Line 2']],
            ],
            'locations' => [['path' => '/tmp/foo.txt', 'line' => 42]],
            'rawInput' => ['path' => '/tmp/foo.txt'],
            'rawOutput' => ['content' => 'hello'],
        ]);

        static::assertInstanceOf(SessionUpdate::class, $update);
        static::assertSame('sess_1', $update->getSessionId());
        static::assertSame('tool_call', $update->getUpdateType());
        static::assertSame('call_1', $update->getToolCallId());
        static::assertSame('Read file', $update->getTitle());
        static::assertSame('read', $update->getKind());
        static::assertSame('completed', $update->getStatus());
        static::assertCount(2, $update->getContentItems());
        static::assertInstanceOf(ContentToolCallContent::class, $update->getContentItems()[0]);
        static::assertInstanceOf(ContentToolCallContent::class, $update->getContentItems()[1]);
        static::assertSame(
            [
                ['type' => 'content', 'content' => ['type' => 'text', 'text' => 'Line 1']],
                ['type' => 'content', 'content' => ['type' => 'text', 'text' => 'Line 2']],
            ],
            $update->getContent(),
        );
        static::assertCount(1, $update->getLocations());
        $loc = $update->getLocations()[0];
        static::assertInstanceOf(ToolCallLocation::class, $loc);
        static::assertSame('/tmp/foo.txt', $loc->getPath());
        static::assertSame(42, $loc->getLine());
        static::assertSame(['path' => '/tmp/foo.txt'], $update->getRawInput());
        static::assertSame(['content' => 'hello'], $update->getRawOutput());
    }

    public function testParsesDiffContent(): void
    {
        $update = ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Edit file',
            'kind' => 'edit',
            'status' => 'completed',
            'content' => [
                [
                    'type' => 'diff',
                    'path' => '/home/user/project/config.json',
                    'oldText' => '{"debug": false}',
                    'newText' => '{"debug": true}',
                ],
            ],
        ]);

        static::assertCount(1, $update->getContentItems());
        $item = $update->getContentItems()[0];
        static::assertInstanceOf(DiffToolCallContent::class, $item);
        static::assertSame('/home/user/project/config.json', $item->getPath());
        static::assertSame('{"debug": true}', $item->getNewText());
        static::assertSame('{"debug": false}', $item->getOldText());
    }

    public function testParsesTerminalContent(): void
    {
        $update = ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Run command',
            'kind' => 'execute',
            'status' => 'in_progress',
            'content' => [
                ['type' => 'terminal', 'terminalId' => 'term_xyz789'],
            ],
        ]);

        static::assertCount(1, $update->getContentItems());
        $item = $update->getContentItems()[0];
        static::assertInstanceOf(TerminalToolCallContent::class, $item);
        static::assertSame('term_xyz789', $item->getTerminalId());
    }

    public function testParsesContentBlockWrapper(): void
    {
        $update = ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Analyze',
            'content' => [
                ['type' => 'content', 'content' => ['type' => 'text', 'text' => 'Analysis complete']],
            ],
        ]);

        static::assertCount(1, $update->getContentItems());
        $item = $update->getContentItems()[0];
        static::assertInstanceOf(ContentToolCallContent::class, $item);
        static::assertInstanceOf(TextContentBlock::class, $item->getContentBlock());
        static::assertSame('Analysis complete', $item->getContentBlock()->getText());
    }

    public function testParsesLocationWithoutLine(): void
    {
        $update = ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Read file',
            'locations' => [['path' => '/tmp/foo.txt']],
        ]);

        $loc = $update->getLocations()[0];
        static::assertSame('/tmp/foo.txt', $loc->getPath());
        static::assertNull($loc->getLine());
    }

    public function testDefaultsForMissingKindAndStatus(): void
    {
        $update = ToolCallUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'tool_call',
            'toolCallId' => 'call_1',
            'title' => 'Unknown action',
        ]);

        static::assertSame('other', $update->getKind());
        static::assertSame('pending', $update->getStatus());
        static::assertSame([], $update->getContent());
        static::assertSame([], $update->getLocations());
        static::assertNull($update->getRawInput());
        static::assertNull($update->getRawOutput());
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
        $this->expectExceptionMessage(
            'Invalid tool_call update: kind must be one of read, edit, delete, move, search, execute, think, fetch, other',
        );

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
        $this->expectExceptionMessage(
            'Invalid tool_call update: status must be one of pending, in_progress, completed, failed',
        );

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
