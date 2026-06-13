<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;

final class ToolCallLocation
{
    public function __construct(
        private readonly string $path,
        private readonly ?int $line = null,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['path' => $this->path];

        if ($this->line !== null) {
            $result['line'] = $this->line;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $path = $data['path'] ?? null;
        if (!is_string($path)) {
            throw new AcpException('Invalid tool call location: path must be a string');
        }

        $line = null;
        if (array_key_exists('line', $data)) {
            if (!is_int($data['line'])) {
                throw new AcpException('Invalid tool call location: line must be an integer');
            }
            $line = $data['line'];
        }

        return new self($path, $line);
    }
}