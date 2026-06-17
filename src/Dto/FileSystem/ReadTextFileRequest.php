<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\FileSystem;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;
use Yankewei\AcpClient\Util\Path;

final class ReadTextFileRequest
{
    public function __construct(
        private readonly string $sessionId,
        private readonly string $path,
        private readonly ?int $line = null,
        private readonly ?int $limit = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $path = Assert::requiredString($data, 'path', 'Invalid fs/read_text_file params: path must be a string');
        if (!Path::isAbsolutePath($path)) {
            throw new AcpException('Invalid fs/read_text_file params: path must be an absolute path');
        }

        return new self(
            Assert::requiredString($data, 'sessionId', 'Invalid fs/read_text_file params: sessionId must be a string'),
            $path,
            Assert::optionalInt($data['line'] ?? null, 'Invalid fs/read_text_file params: line must be an integer'),
            Assert::optionalInt($data['limit'] ?? null, 'Invalid fs/read_text_file params: limit must be an integer'),
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

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }
}
