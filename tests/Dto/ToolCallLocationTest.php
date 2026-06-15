<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ToolCallLocation;
use Yankewei\AcpClient\Exception\AcpException;

final class ToolCallLocationTest extends TestCase
{
    public function testGetPath(): void
    {
        $loc = new ToolCallLocation('/tmp/foo.txt');
        static::assertSame('/tmp/foo.txt', $loc->getPath());
    }

    public function testGetLine(): void
    {
        $loc = new ToolCallLocation('/tmp/foo.txt', 42);
        static::assertSame(42, $loc->getLine());
    }

    public function testLineDefaultsToNull(): void
    {
        $loc = new ToolCallLocation('/tmp/foo.txt');
        static::assertNull($loc->getLine());
    }

    public function testToArrayWithLine(): void
    {
        $loc = new ToolCallLocation('/tmp/foo.txt', 42);
        static::assertSame(['path' => '/tmp/foo.txt', 'line' => 42], $loc->toArray());
    }

    public function testToArrayWithoutLine(): void
    {
        $loc = new ToolCallLocation('/tmp/foo.txt');
        static::assertSame(['path' => '/tmp/foo.txt'], $loc->toArray());
    }

    public function testFromArrayWithLine(): void
    {
        $loc = ToolCallLocation::fromArray(['path' => '/tmp/foo.txt', 'line' => 42]);
        static::assertSame('/tmp/foo.txt', $loc->getPath());
        static::assertSame(42, $loc->getLine());
    }

    public function testFromArrayWithoutLine(): void
    {
        $loc = ToolCallLocation::fromArray(['path' => '/tmp/foo.txt']);
        static::assertSame('/tmp/foo.txt', $loc->getPath());
        static::assertNull($loc->getLine());
    }

    public function testRejectsMissingPath(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool call location: path must be a string');

        ToolCallLocation::fromArray(['line' => 42]);
    }

    public function testRejectsNonStringPath(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool call location: path must be a string');

        ToolCallLocation::fromArray(['path' => 123]);
    }

    public function testRejectsNonIntLine(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid tool call location: line must be an integer');

        ToolCallLocation::fromArray(['path' => '/tmp/foo.txt', 'line' => 'abc']);
    }
}
