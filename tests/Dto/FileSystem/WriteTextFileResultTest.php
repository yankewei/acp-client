<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\FileSystem;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileResult;

final class WriteTextFileResultTest extends TestCase
{
    public function testSerializesToNullResult(): void
    {
        $result = new WriteTextFileResult();

        static::assertNull($result->toResultArray());
    }
}
