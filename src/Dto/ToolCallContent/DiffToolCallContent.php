<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ToolCallContent;

final class DiffToolCallContent implements ToolCallContentInterface
{
    public function __construct(
        private readonly string $path,
        private readonly string $newText,
        private readonly ?string $oldText = null,
    ) {}

    public function getType(): string
    {
        return 'diff';
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getNewText(): string
    {
        return $this->newText;
    }

    public function getOldText(): ?string
    {
        return $this->oldText;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'type' => 'diff',
            'path' => $this->path,
            'newText' => $this->newText,
        ];

        if ($this->oldText !== null) {
            $result['oldText'] = $this->oldText;
        }

        return $result;
    }
}
