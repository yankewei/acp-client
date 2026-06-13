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

        self::assertSame('text', $block->getType());
        self::assertSame('Hello', $block->getText());
        self::assertNull($block->getAnnotations());
        self::assertSame(['type' => 'text', 'text' => 'Hello'], $block->toArray());
    }

    public function testToArrayIncludesAnnotations(): void
    {
        $block = new TextContentBlock('Hello', new Annotations(['audience' => ['user']]));

        self::assertSame(
            ['type' => 'text', 'text' => 'Hello', 'annotations' => ['audience' => ['user']]],
            $block->toArray(),
        );
    }
}
