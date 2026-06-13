<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\ToolCallContent;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\TextContentBlock;
use Yankewei\AcpClient\Dto\ToolCallContent\ContentToolCallContent;
use Yankewei\AcpClient\Dto\ToolCallContent\DiffToolCallContent;
use Yankewei\AcpClient\Dto\ToolCallContent\TerminalToolCallContent;
use Yankewei\AcpClient\Dto\ToolCallContent\ToolCallContentFactory;
use Yankewei\AcpClient\Dto\ToolCallContent\ToolCallContentInterface;
use Yankewei\AcpClient\Exception\AcpException;

final class ToolCallContentFactoryTest extends TestCase
{
    public function testCreatesContentToolCallContent(): void
    {
        $item = ToolCallContentFactory::fromArray([
            'type' => 'content',
            'content' => ['type' => 'text', 'text' => 'Hello'],
        ]);

        self::assertInstanceOf(ContentToolCallContent::class, $item);
        self::assertSame('content', $item->getType());
        self::assertInstanceOf(TextContentBlock::class, $item->getContentBlock());
        self::assertSame('Hello', $item->getContentBlock()->getText());
    }

    public function testCreatesDiffToolCallContent(): void
    {
        $item = ToolCallContentFactory::fromArray([
            'type' => 'diff',
            'path' => '/tmp/foo.txt',
            'oldText' => 'old',
            'newText' => 'new',
        ]);

        self::assertInstanceOf(DiffToolCallContent::class, $item);
        self::assertSame('diff', $item->getType());
        self::assertSame('/tmp/foo.txt', $item->getPath());
        self::assertSame('old', $item->getOldText());
        self::assertSame('new', $item->getNewText());
    }

    public function testCreatesDiffToolCallContentWithoutOldText(): void
    {
        $item = ToolCallContentFactory::fromArray([
            'type' => 'diff',
            'path' => '/tmp/bar.txt',
            'newText' => 'new file content',
        ]);

        self::assertInstanceOf(DiffToolCallContent::class, $item);
        self::assertNull($item->getOldText());
    }

    public function testCreatesTerminalToolCallContent(): void
    {
        $item = ToolCallContentFactory::fromArray([
            'type' => 'terminal',
            'terminalId' => 'term_xyz789',
        ]);

        self::assertInstanceOf(TerminalToolCallContent::class, $item);
        self::assertSame('terminal', $item->getType());
        self::assertSame('term_xyz789', $item->getTerminalId());
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

        self::assertCount(2, $items);
        self::assertInstanceOf(ContentToolCallContent::class, $items[0]);
        self::assertInstanceOf(TerminalToolCallContent::class, $items[1]);
    }

    public function testFromArrayListEmpty(): void
    {
        self::assertSame([], ToolCallContentFactory::fromArrayList([]));
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