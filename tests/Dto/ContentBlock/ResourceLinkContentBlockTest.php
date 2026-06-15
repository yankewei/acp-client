<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\ContentBlock;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\Annotations;
use Yankewei\AcpClient\Dto\ContentBlock\ResourceLinkContentBlock;

final class ResourceLinkContentBlockTest extends TestCase
{
    public function testGettersAndToArray(): void
    {
        $block = new ResourceLinkContentBlock('file:///a.php', 'a.php', 'text/x-php', 'Source', 'A file', 123);

        static::assertSame('resource_link', $block->getType());
        static::assertSame('file:///a.php', $block->getUri());
        static::assertSame('a.php', $block->getName());
        static::assertSame('text/x-php', $block->getMimeType());
        static::assertSame('Source', $block->getTitle());
        static::assertSame('A file', $block->getDescription());
        static::assertSame(123, $block->getSize());
        static::assertSame(
            [
                'type' => 'resource_link',
                'uri' => 'file:///a.php',
                'name' => 'a.php',
                'mimeType' => 'text/x-php',
                'title' => 'Source',
                'description' => 'A file',
                'size' => 123,
            ],
            $block->toArray(),
        );
    }

    public function testToArrayOmitsOptionalFields(): void
    {
        $block = new ResourceLinkContentBlock('file:///a.php', 'a.php');

        static::assertSame(['type' => 'resource_link', 'uri' => 'file:///a.php', 'name' => 'a.php'], $block->toArray());
    }

    public function testToArrayIncludesAnnotations(): void
    {
        $block = new ResourceLinkContentBlock(
            'file:///a.php',
            'a.php',
            null,
            null,
            null,
            null,
            new Annotations(['audience' => ['user']]),
        );

        static::assertSame(
            [
                'type' => 'resource_link',
                'uri' => 'file:///a.php',
                'name' => 'a.php',
                'annotations' => ['audience' => ['user']],
            ],
            $block->toArray(),
        );
    }
}
