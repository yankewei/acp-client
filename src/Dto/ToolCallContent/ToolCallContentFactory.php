<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ToolCallContent;

use Yankewei\AcpClient\Dto\ContentBlock\ContentBlockFactory;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class ToolCallContentFactory
{
    private function __construct() {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): ToolCallContentInterface
    {
        $type = $data['type'] ?? null;
        if (!is_string($type)) {
            throw new AcpException('Invalid tool call content: type must be a string');
        }

        return match ($type) {
            'content' => self::createContent($data),
            'diff' => self::createDiff($data),
            'terminal' => self::createTerminal($data),
            default => throw new AcpException(
                'Invalid tool call content: type is not a supported tool call content type',
            ),
        };
    }

    /**
     * @param mixed $list
     * @return ToolCallContentInterface[]
     *
     * @throws AcpException
     */
    public static function fromArrayList(mixed $list): array
    {
        if (!is_array($list) || !array_is_list($list)) {
            throw new AcpException('Invalid tool call content: must be a list');
        }

        $items = [];
        foreach ($list as $index => $item) {
            if (!is_array($item) || array_is_list($item)) {
                throw new AcpException("Invalid tool call content: entry {$index} must be an object");
            }

            /** @var array<string, mixed> $item */
            $items[] = self::fromArray($item);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    private static function createContent(array $data): ContentToolCallContent
    {
        $content = Assert::requiredObjectField(
            $data,
            'content',
            'Invalid tool call content: content must be an object',
        );

        return new ContentToolCallContent(ContentBlockFactory::fromArray($content));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createDiff(array $data): DiffToolCallContent
    {
        $path = Assert::requiredString($data, 'path', 'Invalid diff tool call content: path must be a string');

        $newText = Assert::requiredString($data, 'newText', 'Invalid diff tool call content: newText must be a string');

        $oldText = Assert::optionalString(
            $data,
            'oldText',
            'Invalid diff tool call content: oldText must be a string or null',
        );

        return new DiffToolCallContent($path, $newText, $oldText);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createTerminal(array $data): TerminalToolCallContent
    {
        $terminalId = Assert::requiredString(
            $data,
            'terminalId',
            'Invalid terminal tool call content: terminalId must be a string',
        );

        return new TerminalToolCallContent($terminalId);
    }
}
