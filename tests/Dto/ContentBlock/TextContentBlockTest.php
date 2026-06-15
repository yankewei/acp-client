<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\ContentBlock;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\Annotations;
use Yankewei\AcpClient\Dto\ContentBlock\TextContentBlock;

final class TextContentBlockTest extends TestCase
{
    public function testGettersAndToArray(): void
    {
        $block = new TextContentBlock('Hello');

        static::assertSame('text', $block->getType());
        static::assertSame('Hello', $block->getText());
        static::assertNull($block->getAnnotations());
        static::assertSame(['type' => 'text', 'text' => 'Hello'], $block->toArray());
    }

    public function testToArrayIncludesAnnotations(): void
    {
        $block = new TextContentBlock('Hello', new Annotations(['audience' => ['user']]));

        static::assertSame(
            ['type' => 'text', 'text' => 'Hello', 'annotations' => ['audience' => ['user']]],
            $block->toArray(),
        );
    }
}
