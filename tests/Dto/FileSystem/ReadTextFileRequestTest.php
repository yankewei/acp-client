<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\FileSystem;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\FileSystem\ReadTextFileRequest;
use Yankewei\AcpClient\Exception\AcpException;

final class ReadTextFileRequestTest extends TestCase
{
    public function testParsesAllFields(): void
    {
        $request = ReadTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
            'line' => 10,
            'limit' => 50,
        ]);

        static::assertSame('sess_1', $request->getSessionId());
        static::assertSame('/repo/a.php', $request->getPath());
        static::assertSame(10, $request->getLine());
        static::assertSame(50, $request->getLimit());
    }

    public function testDefaultsOptionalFieldsToNull(): void
    {
        $request = ReadTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
        ]);

        static::assertNull($request->getLine());
        static::assertNull($request->getLimit());
    }

    public function testRejectsMissingSessionId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/read_text_file params: sessionId must be a string');

        ReadTextFileRequest::fromArray(['path' => '/repo/a.php']);
    }

    public function testRejectsMissingPath(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/read_text_file params: path must be a string');

        ReadTextFileRequest::fromArray(['sessionId' => 'sess_1']);
    }

    public function testRejectsInvalidLineType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/read_text_file params: line must be an integer');

        ReadTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
            'line' => 'ten',
        ]);
    }

    public function testRejectsInvalidLimitType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/read_text_file params: limit must be an integer');

        ReadTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
            'limit' => 'fifty',
        ]);
    }
}
