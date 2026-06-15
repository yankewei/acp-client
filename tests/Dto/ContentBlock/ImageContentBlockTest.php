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

        static::assertSame('image', $block->getType());
        static::assertSame('base64', $block->getData());
        static::assertSame('image/png', $block->getMimeType());
        static::assertSame('https://example.com/img.png', $block->getUri());
        static::assertNull($block->getAnnotations());
        static::assertSame(
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

        static::assertSame(['type' => 'image', 'data' => 'base64', 'mimeType' => 'image/png'], $block->toArray());
    }

    public function testToArrayIncludesAnnotations(): void
    {
        $block = new ImageContentBlock('base64', 'image/png', null, new Annotations([]));

        static::assertSame(
            ['type' => 'image', 'data' => 'base64', 'mimeType' => 'image/png', 'annotations' => []],
            $block->toArray(),
        );
    }
}
