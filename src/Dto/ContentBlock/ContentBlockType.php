<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ContentBlock;

use Yankewei\AcpClient\Dto\InitializeResult;

enum ContentBlockType: string
{
    case Text = 'text';
    case Image = 'image';
    case Audio = 'audio';
    case Resource = 'resource';
    case ResourceLink = 'resource_link';

    /**
     * @return array<int, self>
     */
    public static function supportedValues(): array
    {
        return [
            self::Text,
            self::Image,
            self::Audio,
            self::Resource,
            self::ResourceLink,
        ];
    }

    public function isSupportedBy(InitializeResult $initializeResult): bool
    {
        return match ($this) {
            self::Text, self::ResourceLink => true,
            self::Image => $initializeResult->supportsPromptImage(),
            self::Audio => $initializeResult->supportsPromptAudio(),
            self::Resource => $initializeResult->supportsPromptEmbeddedContext(),
        };
    }

    public function capability(): ?string
    {
        return match ($this) {
            self::Image => 'promptCapabilities.image',
            self::Audio => 'promptCapabilities.audio',
            self::Resource => 'promptCapabilities.embeddedContext',
            default => null,
        };
    }
}
