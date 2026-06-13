<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class PlanUpdate implements SessionUpdate
{
    /**
     * @param PlanEntry[] $entries
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly array $entries,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'plan') {
            throw new AcpException('Invalid plan update: sessionUpdate must be plan');
        }

        $entries = $update['entries'] ?? null;
        if (!is_array($entries) || !array_is_list($entries)) {
            throw new AcpException('Invalid plan update: entries must be a list');
        }

        return new self(
            $sessionId,
            array_map(
                static function (mixed $entry, int $index): PlanEntry {
                    return PlanEntry::fromArray(
                        Assert::object(
                            $entry,
                            "Invalid plan update: entries[{$index}] must be an object",
                        ),
                    );
                },
                $entries,
                array_keys($entries),
            ),
        );
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'plan';
    }

    /**
     * @return PlanEntry[]
     */
    public function getEntries(): array
    {
        return $this->entries;
    }
}

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
