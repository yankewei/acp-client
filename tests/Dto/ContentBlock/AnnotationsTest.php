<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\ContentBlock;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\Annotations;

final class AnnotationsTest extends TestCase
{
    public function testFromArrayReturnsAnnotations(): void
    {
        $annotations = Annotations::fromArray(['audience' => ['user']], 'must be object');

        self::assertInstanceOf(Annotations::class, $annotations);
        self::assertSame(['audience' => ['user']], $annotations->getData());
        self::assertSame(['audience' => ['user']], $annotations->toArray());
    }

    public function testFromArrayReturnsNullForNull(): void
    {
        self::assertNull(Annotations::fromArray(null, 'must be object'));
    }

    public function testFromArrayRejectsNonObject(): void
    {
        $this->expectExceptionMessage('must be object');

        Annotations::fromArray(['invalid'], 'must be object');
    }
}
