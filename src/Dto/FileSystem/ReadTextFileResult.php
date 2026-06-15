<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\FileSystem;

final class ReadTextFileResult
{
    public function __construct(
        private readonly string $content,
    ) {}

    public static function fromString(string $content): self
    {
        return new self($content);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return array{content: string}
     */
    public function toResultArray(): array
    {
        return ['content' => $this->content];
    }
}
