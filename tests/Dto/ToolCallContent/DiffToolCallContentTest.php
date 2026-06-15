<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\ToolCallContent;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ToolCallContent\DiffToolCallContent;

final class DiffToolCallContentTest extends TestCase
{
    public function testGetType(): void
    {
        $diff = new DiffToolCallContent('/tmp/foo.txt', 'new content', 'old content');
        static::assertSame('diff', $diff->getType());
    }

    public function testGetPath(): void
    {
        $diff = new DiffToolCallContent('/tmp/foo.txt', 'new content');
        static::assertSame('/tmp/foo.txt', $diff->getPath());
    }

    public function testGetNewText(): void
    {
        $diff = new DiffToolCallContent('/tmp/foo.txt', 'new content');
        static::assertSame('new content', $diff->getNewText());
    }

    public function testGetOldText(): void
    {
        $diff = new DiffToolCallContent('/tmp/foo.txt', 'new content', 'old content');
        static::assertSame('old content', $diff->getOldText());
    }

    public function testOldTextDefaultsToNull(): void
    {
        $diff = new DiffToolCallContent('/tmp/foo.txt', 'new content');
        static::assertNull($diff->getOldText());
    }

    public function testToArrayWithOldText(): void
    {
        $diff = new DiffToolCallContent('/tmp/foo.txt', 'new', 'old');

        static::assertSame(
            ['type' => 'diff', 'path' => '/tmp/foo.txt', 'newText' => 'new', 'oldText' => 'old'],
            $diff->toArray(),
        );
    }

    public function testToArrayWithoutOldText(): void
    {
        $diff = new DiffToolCallContent('/tmp/foo.txt', 'new content');

        static::assertSame(['type' => 'diff', 'path' => '/tmp/foo.txt', 'newText' => 'new content'], $diff->toArray());
    }
}
