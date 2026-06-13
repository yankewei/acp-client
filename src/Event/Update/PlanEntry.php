<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class PlanEntry
{
    public function __construct(
        private readonly string $content,
        private readonly ?string $priority,
        private readonly ?string $status,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $content = Assert::requiredString(
            $data,
            'content',
            'Invalid plan entry: content must be a string',
        );

        $priority = Assert::optionalString(
            $data,
            'priority',
            'Invalid plan entry: priority must be a string or null',
        );

        $status = Assert::optionalString(
            $data,
            'status',
            'Invalid plan entry: status must be a string or null',
        );

        return new self($content, $priority, $status);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
