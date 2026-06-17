<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\FileSystem;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileRequest;
use Yankewei\AcpClient\Exception\AcpException;

final class WriteTextFileRequestTest extends TestCase
{
    public function testParsesAllFields(): void
    {
        $request = WriteTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
            'content' => '<?php echo "hi";',
        ]);

        static::assertSame('sess_1', $request->getSessionId());
        static::assertSame('/repo/a.php', $request->getPath());
        static::assertSame('<?php echo "hi";', $request->getContent());
    }

    public function testRejectsMissingSessionId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/write_text_file params: sessionId must be a string');

        WriteTextFileRequest::fromArray([
            'path' => '/repo/a.php',
            'content' => 'x',
        ]);
    }

    public function testRejectsMissingPath(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/write_text_file params: path must be a string');

        WriteTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'content' => 'x',
        ]);
    }

    public function testRejectsMissingContent(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/write_text_file params: content must be a string');

        WriteTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
        ]);
    }

    public function testRejectsRelativePath(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/write_text_file params: path must be an absolute path');

        WriteTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => 'repo/a.php',
            'content' => 'x',
        ]);
    }
}
