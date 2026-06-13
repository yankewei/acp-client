<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\ContentBlock;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\Annotations;
use Yankewei\AcpClient\Dto\ContentBlock\ResourceContentBlock;
use Yankewei\AcpClient\Exception\AcpException;

final class ResourceContentBlockTest extends TestCase
{
    public function testTextResourceGettersAndToArray(): void
    {
        $block = new ResourceContentBlock('file:///a.php', 'contents', null, 'text/x-php');

        self::assertSame('resource', $block->getType());
        self::assertSame('file:///a.php', $block->getUri());
        self::assertSame('contents', $block->getText());
        self::assertNull($block->getBlob());
        self::assertSame('text/x-php', $block->getMimeType());
        self::assertSame(
            [
                'type' => 'resource',
                'resource' => [
                    'uri' => 'file:///a.php',
                    'text' => 'contents',
                    'mimeType' => 'text/x-php',
                ],
            ],
            $block->toArray(),
        );
    }

    public function testBlobResourceGettersAndToArray(): void
    {
        $block = new ResourceContentBlock('file:///a.bin', null, 'base64', 'application/octet-stream');

        self::assertNull($block->getText());
        self::assertSame('base64', $block->getBlob());
        self::assertSame(
            [
                'type' => 'resource',
                'resource' => [
                    'uri' => 'file:///a.bin',
                    'blob' => 'base64',
                    'mimeType' => 'application/octet-stream',
                ],
            ],
            $block->toArray(),
        );
    }

    public function testToArrayOmitsOptionalMimeType(): void
    {
        $block = ResourceContentBlock::resourceFromArray(['uri' => 'file:///a.php', 'text' => 'contents']);

        self::assertSame(
            ['type' => 'resource', 'resource' => ['uri' => 'file:///a.php', 'text' => 'contents']],
            $block->toArray(),
        );
    }

    public function testToArrayIncludesAnnotations(): void
    {
        $block = new ResourceContentBlock('file:///a.php', 'contents', null, null, new Annotations([]));

        self::assertSame(
            [
                'type' => 'resource',
                'resource' => ['uri' => 'file:///a.php', 'text' => 'contents'],
                'annotations' => [],
            ],
            $block->toArray(),
        );
    }

    public function testResourceFromArrayRejectsMissingUri(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid resource content block: resource.uri must be a string');

        ResourceContentBlock::resourceFromArray(['text' => 'contents']);
    }

    public function testResourceFromArrayRejectsMissingTextAndBlob(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid resource content block: resource must include text or blob');

        ResourceContentBlock::resourceFromArray(['uri' => 'file:///a.php']);
    }

    public function testResourceFromArrayRejectsBothTextAndBlob(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid resource content block: resource cannot include both text and blob');

        ResourceContentBlock::resourceFromArray([
            'uri' => 'file:///a.php',
            'text' => 'contents',
            'blob' => 'base64',
        ]);
    }

    public function testResourceFromArrayRejectsInvalidTextType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid resource content block: resource.text must be a string');

        ResourceContentBlock::resourceFromArray(['uri' => 'file:///a.php', 'text' => 123]);
    }

    public function testResourceFromArrayRejectsInvalidBlobType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid resource content block: resource.blob must be a string');

        ResourceContentBlock::resourceFromArray(['uri' => 'file:///a.php', 'blob' => 123]);
    }

    public function testResourceFromArrayRejectsInvalidMimeType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid resource content block: resource.mimeType must be a string');

        ResourceContentBlock::resourceFromArray([
            'uri' => 'file:///a.php',
            'text' => 'contents',
            'mimeType' => 123,
        ]);
    }
}
