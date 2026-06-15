<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ContentBlock;

final class TextContentBlock implements ContentBlockInterface
{
    public function __construct(
        private readonly string $text,
        private readonly ?Annotations $annotations = null,
    ) {}

    public function getType(): string
    {
        return 'text';
    }

    public function getText(): string
    {
        return $this->text;
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
        $data = [
            'type' => 'text',
            'text' => $this->text,
        ];

        if ($this->annotations !== null) {
            $data['annotations'] = $this->annotations->toArray();
        }

        return $data;
    }
}
