<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\ToolCallContent;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\TextContentBlock;
use Yankewei\AcpClient\Dto\ToolCallContent\ContentToolCallContent;
use Yankewei\AcpClient\Dto\ToolCallContent\DiffToolCallContent;
use Yankewei\AcpClient\Dto\ToolCallContent\TerminalToolCallContent;
use Yankewei\AcpClient\Dto\ToolCallContent\ToolCallContentFactory;
use Yankewei\AcpClient\Exception\AcpException;

final class ToolCallContentFactoryTest extends TestCase
{
    public function testCreatesContentToolCallContent(): void
    {
        $item = ToolCallContentFactory::fromArray([
            'type' => 'content',
            'content' => ['type' => 'text', 'text' => 'Hello'],
        ]);

        static::assertInstanceOf(ContentToolCallContent::class, $item);
        static::assertSame('content', $item->getType());
        static::assertInstanceOf(TextContentBlock::class, $block = $item->getContentBlock());
        static::assertSame('Hello', $block->getText());
    }

    public function testCreatesDiffToolCallContent(): void
    {
        $item = ToolCallContentFactory::fromArray([
            'type' => 'diff',
            'path' => '/tmp/foo.txt',
            'oldText' => 'old',
            'newText' => 'new',
        ]);

        static::assertInstanceOf(DiffToolCallContent::class, $item);
        static::assertSame('diff', $item->getType());
        static::assertSame('/tmp/foo.txt', $item->getPath());
        static::assertSame('old', $item->getOldText());
        static::assertSame('new', $item->getNewText());
    }

    public function testCreatesDiffToolCallContentWithoutOldText(): void
    {
        $item = ToolCallContentFactory::fromArray([
            'type' => 'diff',
            'path' => '/tmp/bar.txt',
            'newText' => 'new file content',
        ]);

        static::assertInstanceOf(DiffToolCallContent::class, $item);
        static::assertNull($item->getOldText());
    }

    public function testCreatesTerminalToolCallContent(): void
    {
        $item = ToolCallContentFactory::fromArray([
            'type' => 'terminal',
            'terminalId' => 'term_xyz789',
        ]);

        static::assertInstanceOf(TerminalToolCallContent::class, $item);
        static::assertSame('terminal', $item->getType());
        static::assertSame('term_xyz789', $item->getTerminalId());
    }

    public function testRejectsUnsupportedType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool call content: type is not a supported tool call content type');

        ToolCallContentFactory::fromArray(['type' => 'unknown']);
    }

    public function testRejectsMissingType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool call content: type must be a string');

        ToolCallContentFactory::fromArray(['path' => '/tmp/foo']);
    }

    public function testFromArrayList(): void
    {
        $items = ToolCallContentFactory::fromArrayList([
            ['type' => 'content', 'content' => ['type' => 'text', 'text' => 'hi']],
            ['type' => 'terminal', 'terminalId' => 't1'],
        ]);

        static::assertCount(2, $items);
        static::assertInstanceOf(ContentToolCallContent::class, $items[0]);
        static::assertInstanceOf(TerminalToolCallContent::class, $items[1]);
    }

    public function testFromArrayListEmpty(): void
    {
        static::assertSame([], ToolCallContentFactory::fromArrayList([]));
    }

    public function testRejectsNonListArray(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool call content: must be a list');

        ToolCallContentFactory::fromArrayList(['key' => 'value']);
    }

    public function testRejectsNonArrayItem(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool call content: entry 0 must be an object');

        ToolCallContentFactory::fromArrayList(['not an array']);
    }

    public function testRejectsContentWithMissingContentField(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool call content: content must be an object');

        ToolCallContentFactory::fromArray(['type' => 'content']);
    }

    public function testRejectsDiffWithMissingPath(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid diff tool call content: path must be a string');

        ToolCallContentFactory::fromArray(['type' => 'diff', 'newText' => 'new']);
    }

    public function testRejectsDiffWithMissingNewText(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid diff tool call content: newText must be a string');

        ToolCallContentFactory::fromArray(['type' => 'diff', 'path' => '/tmp/foo']);
    }

    public function testRejectsTerminalWithMissingId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid terminal tool call content: terminalId must be a string');

        ToolCallContentFactory::fromArray(['type' => 'terminal']);
    }
}
