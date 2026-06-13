<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Dto\ToolCallContent\ToolCallContentFactory;
use Yankewei\AcpClient\Dto\ToolCallContent\ToolCallContentInterface;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class ToolCallStatusUpdate implements SessionUpdate
{
    /** @var string[] */
    private const STATUSES = ['pending', 'in_progress', 'completed', 'failed'];

    /**
     * @param ToolCallContentInterface[] $content
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly string $toolCallId,
        private readonly ?string $status,
        private readonly array $content,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'tool_call_update') {
            throw new AcpException('Invalid tool_call_update update: sessionUpdate must be tool_call_update');
        }

        $toolCallId = Assert::requiredString(
            $update,
            'toolCallId',
            'Invalid tool_call_update update: toolCallId must be a string',
        );

        $status = Assert::optionalStringInEnum(
            $update['status'] ?? null,
            self::STATUSES,
            'Invalid tool_call_update update: status must be one of pending, in_progress, completed, failed',
        );

        $contentList = Assert::optionalList(
            $update['content'] ?? null,
            'Invalid tool_call_update update: content must be a list',
        );

        /** @var array<int, array<string, mixed>> $contentList */
        $content = ToolCallContentFactory::fromArrayList($contentList);

        return new self(
            $sessionId,
            $toolCallId,
            $status,
            $content,
        );
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'tool_call_update';
    }

    public function getToolCallId(): string
    {
        return $this->toolCallId;
    }

    public function getStatus(): ?string
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
     * @return array<int, array<string, mixed>>
     */
    public function getContent(): array
    {
        return array_map(
            static fn (ToolCallContentInterface $item): array => $item->toArray(),
            $this->content,
        );
    }
}