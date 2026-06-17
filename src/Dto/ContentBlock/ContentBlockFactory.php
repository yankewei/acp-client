<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ContentBlock;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class ContentBlockFactory
{
    private function __construct() {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): ContentBlockInterface
    {
        $type = $data['type'] ?? null;
        if (!is_string($type)) {
            throw new AcpException('Invalid content block: type must be a string');
        }

        $annotations = Annotations::fromArray(
            $data['annotations'] ?? null,
            'Invalid content block: annotations must be an object',
        );

        return match ($type) {
            'text' => self::createText($data, $annotations),
            'image' => self::createImage($data, $annotations),
            'audio' => self::createAudio($data, $annotations),
            'resource' => self::createResource($data, $annotations),
            'resource_link' => self::createResourceLink($data, $annotations),
            default => throw new AcpException('Invalid content block: type is not a supported content block type'),
        };
    }

    /**
     * @param mixed $list
     * @return ContentBlockInterface[]
     *
     * @throws AcpException
     */
    public static function fromArrayList(mixed $list): array
    {
        $list = Assert::list($list, 'Invalid content blocks: must be a list');

        $blocks = [];
        foreach ($list as $index => $item) {
            $item = Assert::object($item, "Invalid content blocks: entry {$index} must be an object");

            $blocks[] = self::fromArray($item);
        }

        return $blocks;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createText(array $data, ?Annotations $annotations): TextContentBlock
    {
        return new TextContentBlock(
            Assert::requiredString($data, 'text', 'Invalid text content block: text must be a string'),
            $annotations,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createImage(array $data, ?Annotations $annotations): ImageContentBlock
    {
        $dataValue = Assert::requiredString($data, 'data', 'Invalid image content block: data must be a string');

        $mimeType = Assert::requiredString($data, 'mimeType', 'Invalid image content block: mimeType must be a string');

        $uri = Assert::optionalString($data, 'uri', 'Invalid image content block: uri must be a string or null');

        return new ImageContentBlock($dataValue, $mimeType, $uri, $annotations);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createAudio(array $data, ?Annotations $annotations): AudioContentBlock
    {
        $dataValue = Assert::requiredString($data, 'data', 'Invalid audio content block: data must be a string');

        $mimeType = Assert::requiredString($data, 'mimeType', 'Invalid audio content block: mimeType must be a string');

        return new AudioContentBlock($dataValue, $mimeType, $annotations);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createResource(array $data, ?Annotations $annotations): ResourceContentBlock
    {
        $resource = Assert::requiredObjectField(
            $data,
            'resource',
            'Invalid resource content block: resource must be an object',
        );

        return ResourceContentBlock::resourceFromArray($resource, $annotations);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createResourceLink(array $data, ?Annotations $annotations): ResourceLinkContentBlock
    {
        $uri = Assert::requiredString($data, 'uri', 'Invalid resource_link content block: uri must be a string');

        $name = Assert::requiredString($data, 'name', 'Invalid resource_link content block: name must be a string');

        $mimeType = Assert::optionalString(
            $data,
            'mimeType',
            'Invalid resource_link content block: mimeType must be a string or null',
        );

        $title = Assert::optionalString(
            $data,
            'title',
            'Invalid resource_link content block: title must be a string or null',
        );

        $description = Assert::optionalString(
            $data,
            'description',
            'Invalid resource_link content block: description must be a string or null',
        );

        $size = null;
        if (array_key_exists('size', $data)) {
            if (!is_int($data['size'])) {
                throw new AcpException('Invalid resource_link content block: size must be an integer');
            }

            $size = $data['size'];
        }

        return new ResourceLinkContentBlock($uri, $name, $mimeType, $title, $description, $size, $annotations);
    }
}
