<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Dto\ToolCallContent\ToolCallContentFactory;
use Yankewei\AcpClient\Dto\ToolCallContent\ToolCallContentInterface;
use Yankewei\AcpClient\Dto\ToolCallLocation;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class ToolCallUpdate implements SessionUpdate
{
    /** @var string[] */
    private const KINDS = ['read', 'edit', 'delete', 'move', 'search', 'execute', 'think', 'fetch', 'other'];

    /** @var string[] */
    private const STATUSES = ['pending', 'in_progress', 'completed', 'failed'];

    /**
     * @param ToolCallContentInterface[] $content
     * @param ToolCallLocation[] $locations
     * @param array<string, mixed>|null $rawInput
     * @param array<string, mixed>|null $rawOutput
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly string $toolCallId,
        private readonly string $title,
        private readonly string $kind,
        private readonly string $status,
        private readonly array $content,
        private readonly array $locations,
        private readonly ?array $rawInput,
        private readonly ?array $rawOutput,
    ) {}

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'tool_call') {
            throw new AcpException('Invalid tool_call update: sessionUpdate must be tool_call');
        }

        $toolCallId = Assert::requiredString(
            $update,
            'toolCallId',
            'Invalid tool_call update: toolCallId must be a string',
        );

        $title = Assert::requiredString($update, 'title', 'Invalid tool_call update: title must be a string');

        $kind =
            Assert::optionalStringInEnum(
                $update['kind'] ?? null,
                self::KINDS,
                'Invalid tool_call update: kind must be one of read, edit, delete, move, search, execute, think, fetch, other',
            ) ?? 'other';

        $status =
            Assert::optionalStringInEnum(
                $update['status'] ?? null,
                self::STATUSES,
                'Invalid tool_call update: status must be one of pending, in_progress, completed, failed',
            ) ?? 'pending';

        $contentList = Assert::optionalList(
            $update['content'] ?? null,
            'Invalid tool_call update: content must be a list',
        );

        /** @var array<int, array<string, mixed>> $contentList */
        $content = ToolCallContentFactory::fromArrayList($contentList);

        $locationsList = Assert::optionalList(
            $update['locations'] ?? null,
            'Invalid tool_call update: locations must be a list',
        );

        /** @var array<int, array<string, mixed>> $locationsList */
        $locations = array_map(static fn(array $loc): ToolCallLocation => ToolCallLocation::fromArray(
            $loc,
        ), $locationsList);

        $rawInput = Assert::optionalObjectField(
            $update,
            'rawInput',
            'Invalid tool_call update: rawInput must be an object',
        );

        $rawOutput = Assert::optionalObjectField(
            $update,
            'rawOutput',
            'Invalid tool_call update: rawOutput must be an object',
        );

        return new self($sessionId, $toolCallId, $title, $kind, $status, $content, $locations, $rawInput, $rawOutput);
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'tool_call';
    }

    public function getToolCallId(): string
    {
        return $this->toolCallId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return ToolCallContentInterface[]
     */
    public function getContentItems(): array
    {
        return $this->content;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getContent(): array
    {
        return array_values(array_map(
            static fn(ToolCallContentInterface $item): array => $item->toArray(),
            $this->content,
        ));
    }

    /**
     * @return ToolCallLocation[]
     */
    public function getLocations(): array
    {
        return $this->locations;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRawInput(): ?array
    {
        return $this->rawInput;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRawOutput(): ?array
    {
        return $this->rawOutput;
    }
}
