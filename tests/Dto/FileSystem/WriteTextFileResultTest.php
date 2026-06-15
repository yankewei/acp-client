<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\FileSystem;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileResult;

final class WriteTextFileResultTest extends TestCase
{
    public function testSerializesToEmptyResultArray(): void
    {
        $result = new WriteTextFileResult();

        static::assertSame([], $result->toResultArray());
    }
}
