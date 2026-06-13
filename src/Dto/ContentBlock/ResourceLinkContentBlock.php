<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ContentBlock;

final class ResourceLinkContentBlock implements ContentBlockInterface
{
    public function __construct(
        private readonly string $uri,
        private readonly string $name,
        private readonly ?string $mimeType = null,
        private readonly ?string $title = null,
        private readonly ?string $description = null,
        private readonly ?int $size = null,
        private readonly ?Annotations $annotations = null,
    ) {
    }

    public function getType(): string
    {
        return 'resource_link';
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSize(): ?int
    {
        return $this->size;
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
            'type' => 'resource_link',
            'uri' => $this->uri,
            'name' => $this->name,
        ];

        if ($this->mimeType !== null) {
            $result['mimeType'] = $this->mimeType;
        }

        if ($this->title !== null) {
            $result['title'] = $this->title;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->size !== null) {
            $result['size'] = $this->size;
        }

        if ($this->annotations !== null) {
            $result['annotations'] = $this->annotations->toArray();
        }

        return $result;
    }
}
