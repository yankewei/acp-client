<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\ContentBlock;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\Annotations;
use Yankewei\AcpClient\Dto\ContentBlock\AudioContentBlock;

final class AudioContentBlockTest extends TestCase
{
    public function testGettersAndToArray(): void
    {
        $block = new AudioContentBlock('base64', 'audio/wav');

        static::assertSame('audio', $block->getType());
        static::assertSame('base64', $block->getData());
        static::assertSame('audio/wav', $block->getMimeType());
        static::assertNull($block->getAnnotations());
        static::assertSame(['type' => 'audio', 'data' => 'base64', 'mimeType' => 'audio/wav'], $block->toArray());
    }

    public function testToArrayIncludesAnnotations(): void
    {
        $block = new AudioContentBlock('base64', 'audio/wav', new Annotations(['foo' => 'bar']));

        static::assertSame(
            ['type' => 'audio', 'data' => 'base64', 'mimeType' => 'audio/wav', 'annotations' => ['foo' => 'bar']],
            $block->toArray(),
        );
    }
}
