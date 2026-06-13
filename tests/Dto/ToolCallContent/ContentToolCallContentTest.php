<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\ToolCallContent;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\TextContentBlock;
use Yankewei\AcpClient\Dto\ToolCallContent\ContentToolCallContent;

final class ContentToolCallContentTest extends TestCase
{
    public function testGetType(): void
    {
        $block = new TextContentBlock('Hello');
        $content = new ContentToolCallContent($block);

        self::assertSame('content', $content->getType());
    }

    public function testGetContentBlock(): void
    {
        $block = new TextContentBlock('Hello');
        $content = new ContentToolCallContent($block);

        self::assertSame($block, $content->getContentBlock());
    }

    public function testToArray(): void
    {
        $block = new TextContentBlock('Hello');
        $content = new ContentToolCallContent($block);

        self::assertSame(
            ['type' => 'content', 'content' => ['type' => 'text', 'text' => 'Hello']],
            $content->toArray(),
        );
    }
}