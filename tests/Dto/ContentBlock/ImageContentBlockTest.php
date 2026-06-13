<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\ContentBlock;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\Annotations;
use Yankewei\AcpClient\Dto\ContentBlock\ImageContentBlock;

final class ImageContentBlockTest extends TestCase
{
    public function testGettersAndToArray(): void
    {
        $block = new ImageContentBlock('base64', 'image/png', 'https://example.com/img.png');

        self::assertSame('image', $block->getType());
        self::assertSame('base64', $block->getData());
        self::assertSame('image/png', $block->getMimeType());
        self::assertSame('https://example.com/img.png', $block->getUri());
        self::assertNull($block->getAnnotations());
        self::assertSame(
            [
                'type' => 'image',
                'data' => 'base64',
                'mimeType' => 'image/png',
                'uri' => 'https://example.com/img.png',
            ],
            $block->toArray(),
        );
    }

    public function testToArrayOmitsOptionalFields(): void
    {
        $block = new ImageContentBlock('base64', 'image/png');

        self::assertSame(
            ['type' => 'image', 'data' => 'base64', 'mimeType' => 'image/png'],
            $block->toArray(),
        );
    }

    public function testToArrayIncludesAnnotations(): void
    {
        $block = new ImageContentBlock('base64', 'image/png', null, new Annotations([]));

        self::assertSame(
            ['type' => 'image', 'data' => 'base64', 'mimeType' => 'image/png', 'annotations' => []],
            $block->toArray(),
        );
    }
}
