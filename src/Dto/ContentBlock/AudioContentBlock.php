<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ContentBlock;

final class AudioContentBlock implements ContentBlockInterface
{
    public function __construct(
        private readonly string $data,
        private readonly string $mimeType,
        private readonly ?Annotations $annotations = null,
    ) {}

    public function getType(): string
    {
        return 'audio';
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getAnnotations(): ?Annotations
    {
        return $this->annotations;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'type' => 'audio',
            'data' => $this->data,
            'mimeType' => $this->mimeType,
        ];

        if ($this->annotations !== null) {
            $result['annotations'] = $this->annotations->toArray();
        }

        return $result;
    }
}
