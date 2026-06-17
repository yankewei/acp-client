<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\FileSystem;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;
use Yankewei\AcpClient\Util\Path;

final class WriteTextFileRequest
{
    public function __construct(
        private readonly string $sessionId,
        private readonly string $path,
        private readonly string $content,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $path = Assert::requiredString($data, 'path', 'Invalid fs/write_text_file params: path must be a string');
        if (!Path::isAbsolutePath($path)) {
            throw new AcpException('Invalid fs/write_text_file params: path must be an absolute path');
        }

        return new self(
            Assert::requiredString($data, 'sessionId', 'Invalid fs/write_text_file params: sessionId must be a string'),
            $path,
            Assert::requiredString($data, 'content', 'Invalid fs/write_text_file params: content must be a string'),
        );
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
