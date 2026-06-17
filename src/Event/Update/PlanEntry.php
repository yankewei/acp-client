<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class PlanEntry
{
    private const PRIORITIES = ['high', 'medium', 'low'];

    private const STATUSES = ['pending', 'in_progress', 'completed'];

    public function __construct(
        private readonly string $content,
        private readonly string $priority,
        private readonly string $status,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $content = Assert::requiredString($data, 'content', 'Invalid plan entry: content must be a string');

        $priority = Assert::requiredStringInEnum(
            $data,
            'priority',
            self::PRIORITIES,
            'Invalid plan entry: priority must be one of high, medium, low',
        );

        $status = Assert::requiredStringInEnum(
            $data,
            'status',
            self::STATUSES,
            'Invalid plan entry: status must be one of pending, in_progress, completed',
        );

        return new self($content, $priority, $status);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
