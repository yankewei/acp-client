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
    ) {}

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

        $entries = Assert::list($update['entries'] ?? null, 'Invalid plan update: entries must be a list');

        return new self($sessionId, array_map(
            static fn(mixed $entry, int $index): PlanEntry => PlanEntry::fromArray(Assert::object(
                $entry,
                "Invalid plan update: entries[{$index}] must be an object",
            )),
            $entries,
            array_keys($entries),
        ));
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
