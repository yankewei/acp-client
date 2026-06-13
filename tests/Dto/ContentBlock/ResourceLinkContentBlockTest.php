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
        $block = new ResourceLinkContentBlock(
            'file:///a.php',
            'a.php',
            'text/x-php',
            'Source',
            'A file',
            123,
        );

        self::assertSame('resource_link', $block->getType());
        self::assertSame('file:///a.php', $block->getUri());
        self::assertSame('a.php', $block->getName());
        self::assertSame('text/x-php', $block->getMimeType());
        self::assertSame('Source', $block->getTitle());
        self::assertSame('A file', $block->getDescription());
        self::assertSame(123, $block->getSize());
        self::assertSame(
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

        self::assertSame(
            ['type' => 'resource_link', 'uri' => 'file:///a.php', 'name' => 'a.php'],
            $block->toArray(),
        );
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

        self::assertSame(
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
