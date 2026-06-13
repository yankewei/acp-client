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

        self::assertSame('audio', $block->getType());
        self::assertSame('base64', $block->getData());
        self::assertSame('audio/wav', $block->getMimeType());
        self::assertNull($block->getAnnotations());
        self::assertSame(
            ['type' => 'audio', 'data' => 'base64', 'mimeType' => 'audio/wav'],
            $block->toArray(),
        );
    }

    public function testToArrayIncludesAnnotations(): void
    {
        $block = new AudioContentBlock('base64', 'audio/wav', new Annotations(['foo' => 'bar']));

        self::assertSame(
            ['type' => 'audio', 'data' => 'base64', 'mimeType' => 'audio/wav', 'annotations' => ['foo' => 'bar']],
            $block->toArray(),
        );
    }
}
