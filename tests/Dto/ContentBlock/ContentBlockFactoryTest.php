<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\ContentBlock;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\AudioContentBlock;
use Yankewei\AcpClient\Dto\ContentBlock\ContentBlockFactory;
use Yankewei\AcpClient\Dto\ContentBlock\ContentBlockInterface;
use Yankewei\AcpClient\Dto\ContentBlock\ImageContentBlock;
use Yankewei\AcpClient\Dto\ContentBlock\ResourceContentBlock;
use Yankewei\AcpClient\Dto\ContentBlock\ResourceLinkContentBlock;
use Yankewei\AcpClient\Dto\ContentBlock\TextContentBlock;
use Yankewei\AcpClient\Exception\AcpException;

final class ContentBlockFactoryTest extends TestCase
{
    public function testCreatesTextBlock(): void
    {
        $block = ContentBlockFactory::fromArray(['type' => 'text', 'text' => 'Hello']);

        self::assertInstanceOf(TextContentBlock::class, $block);
        self::assertInstanceOf(ContentBlockInterface::class, $block);
        self::assertSame('Hello', $block->getText());
    }

    public function testCreatesImageBlock(): void
    {
        $block = ContentBlockFactory::fromArray([
            'type' => 'image',
            'data' => 'base64',
            'mimeType' => 'image/png',
        ]);

        self::assertInstanceOf(ImageContentBlock::class, $block);
        self::assertSame('base64', $block->getData());
        self::assertSame('image/png', $block->getMimeType());
    }

    public function testCreatesAudioBlock(): void
    {
        $block = ContentBlockFactory::fromArray([
            'type' => 'audio',
            'data' => 'base64',
            'mimeType' => 'audio/wav',
        ]);

        self::assertInstanceOf(AudioContentBlock::class, $block);
        self::assertSame('audio/wav', $block->getMimeType());
    }

    public function testCreatesResourceBlock(): void
    {
        $block = ContentBlockFactory::fromArray([
            'type' => 'resource',
            'resource' => ['uri' => 'file:///a.php', 'text' => 'contents'],
        ]);

        self::assertInstanceOf(ResourceContentBlock::class, $block);
        self::assertSame('file:///a.php', $block->getUri());
        self::assertSame('contents', $block->getText());
    }

    public function testCreatesResourceLinkBlock(): void
    {
        $block = ContentBlockFactory::fromArray([
            'type' => 'resource_link',
            'uri' => 'file:///a.php',
            'name' => 'a.php',
        ]);

        self::assertInstanceOf(ResourceLinkContentBlock::class, $block);
        self::assertSame('file:///a.php', $block->getUri());
        self::assertSame('a.php', $block->getName());
    }

    public function testFromArrayRejectsMissingType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid content block: type must be a string');

        ContentBlockFactory::fromArray(['text' => 'Hello']);
    }

    public function testFromArrayRejectsUnsupportedType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid content block: type is not a supported content block type');

        ContentBlockFactory::fromArray(['type' => 'video']);
    }

    public function testFromArrayRejectsInvalidAnnotations(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid content block: annotations must be an object');

        ContentBlockFactory::fromArray(['type' => 'text', 'text' => 'Hello', 'annotations' => ['invalid']]);
    }

    public function testFromArrayListCreatesBlocks(): void
    {
        $blocks = ContentBlockFactory::fromArrayList([
            ['type' => 'text', 'text' => 'Hello'],
            ['type' => 'text', 'text' => 'World'],
        ]);

        self::assertCount(2, $blocks);
        self::assertInstanceOf(TextContentBlock::class, $blocks[0]);
        self::assertInstanceOf(TextContentBlock::class, $blocks[1]);
        self::assertSame('Hello', $blocks[0]->getText());
        self::assertSame('World', $blocks[1]->getText());
    }

    public function testFromArrayListRejectsAssociativeArray(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid content blocks: must be a list');

        ContentBlockFactory::fromArrayList(['type' => 'text', 'text' => 'Hello']);
    }

    public function testFromArrayListRejectsNonObjectEntry(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid content blocks: entry 1 must be an object');

        ContentBlockFactory::fromArrayList([
            ['type' => 'text', 'text' => 'Hello'],
            'not-an-object',
        ]);
    }

    public function testRoundTripThroughToArray(): void
    {
        $data = [
            'type' => 'resource_link',
            'uri' => 'file:///a.php',
            'name' => 'a.php',
            'mimeType' => 'text/x-php',
            'title' => 'Source',
            'description' => 'A file',
            'size' => 123,
        ];

        $block = ContentBlockFactory::fromArray($data);

        self::assertSame($data, $block->toArray());
    }
}
