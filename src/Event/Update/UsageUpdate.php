<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class UsageUpdate implements SessionUpdate
{
    /**
     * @param array<string, mixed>|null $cost
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly int $used,
        private readonly int $size,
        private readonly ?array $cost,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'usage_update') {
            throw new AcpException('Invalid usage_update update: sessionUpdate must be usage_update');
        }

        $used = Assert::requiredInt(
            $update,
            'used',
            'Invalid usage_update update: used must be an integer',
        );

        $size = Assert::requiredInt(
            $update,
            'size',
            'Invalid usage_update update: size must be an integer',
        );

        $cost = null;
        if (array_key_exists('cost', $update)) {
            $cost = Assert::object(
                $update['cost'],
                'Invalid usage_update update: cost must be an object',
            );

            if (!array_key_exists('amount', $cost) || (!is_int($cost['amount']) && !is_float($cost['amount']) && !is_string($cost['amount']))) {
                throw new AcpException('Invalid usage_update update: cost.amount must be a number');
            }

            Assert::requiredString(
                $cost,
                'currency',
                'Invalid usage_update update: cost.currency must be a string',
            );
        }

        return new self($sessionId, $used, $size, $cost);
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'usage_update';
    }

    public function getUsed(): int
    {
        return $this->used;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCost(): ?array
    {
        return $this->cost;
    }
}
