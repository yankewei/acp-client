<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\FileSystem;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\FileSystem\ReadTextFileResult;

final class ReadTextFileResultTest extends TestCase
{
    public function testSerializesToResultArray(): void
    {
        $result = new ReadTextFileResult('hello world');

        static::assertSame(['content' => 'hello world'], $result->toResultArray());
    }

    public function testFromStringFactory(): void
    {
        $result = ReadTextFileResult::fromString('file contents');

        static::assertSame('file contents', $result->getContent());
        static::assertSame(['content' => 'file contents'], $result->toResultArray());
    }
}
