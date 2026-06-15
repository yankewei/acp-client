<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ContentBlock;

use Yankewei\AcpClient\Exception\AcpException;

final class ResourceContentBlock implements ContentBlockInterface
{
    public function __construct(
        private readonly string $uri,
        private readonly ?string $text,
        private readonly ?string $blob,
        private readonly ?string $mimeType,
        private readonly ?Annotations $annotations = null,
    ) {}

    public function getType(): string
    {
        return 'resource';
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getBlob(): ?string
    {
        return $this->blob;
    }

    public function getMimeType(): ?string
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
        $resource = ['uri' => $this->uri];

        if ($this->text !== null) {
            $resource['text'] = $this->text;
        }

        if ($this->blob !== null) {
            $resource['blob'] = $this->blob;
        }

        if ($this->mimeType !== null) {
            $resource['mimeType'] = $this->mimeType;
        }

        $result = [
            'type' => 'resource',
            'resource' => $resource,
        ];

        if ($this->annotations !== null) {
            $result['annotations'] = $this->annotations->toArray();
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @throws AcpException
     */
    public static function resourceFromArray(array $resource, ?Annotations $annotations = null): self
    {
        $uri = $resource['uri'] ?? null;
        if (!is_string($uri)) {
            throw new AcpException('Invalid resource content block: resource.uri must be a string');
        }

        $hasText = array_key_exists('text', $resource);
        $hasBlob = array_key_exists('blob', $resource);

        if (!$hasText && !$hasBlob) {
            throw new AcpException('Invalid resource content block: resource must include text or blob');
        }

        if ($hasText && $hasBlob) {
            throw new AcpException('Invalid resource content block: resource cannot include both text and blob');
        }

        $text = $hasText ? $resource['text'] : null;
        if ($text !== null && !is_string($text)) {
            throw new AcpException('Invalid resource content block: resource.text must be a string');
        }

        $blob = $hasBlob ? $resource['blob'] : null;
        if ($blob !== null && !is_string($blob)) {
            throw new AcpException('Invalid resource content block: resource.blob must be a string');
        }

        $mimeType = $resource['mimeType'] ?? null;
        if ($mimeType !== null && !is_string($mimeType)) {
            throw new AcpException('Invalid resource content block: resource.mimeType must be a string');
        }

        return new self($uri, $text, $blob, $mimeType, $annotations);
    }
}
