<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;

final class PromptResult
{
    public const STOP_REASON_END_TURN = 'end_turn';
    public const STOP_REASON_MAX_TOKENS = 'max_tokens';
    public const STOP_REASON_MAX_TURN_REQUESTS = 'max_turn_requests';
    public const STOP_REASON_REFUSAL = 'refusal';
    public const STOP_REASON_CANCELLED = 'cancelled';

    /** @var array<string, true> */
    private const STOP_REASONS = [
        self::STOP_REASON_END_TURN => true,
        self::STOP_REASON_MAX_TOKENS => true,
        self::STOP_REASON_MAX_TURN_REQUESTS => true,
        self::STOP_REASON_REFUSAL => true,
        self::STOP_REASON_CANCELLED => true,
    ];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $stopReason,
        private readonly array $data,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $stopReason = DtoHelper::requireString($data, 'stopReason');
        if (!array_key_exists($stopReason, self::STOP_REASONS)) {
            throw new AcpException('Invalid session/prompt response: stopReason is not supported');
        }

        return new self($stopReason, $data);
    }

    public function getStopReason(): string
    {
        return $this->stopReason;
    }

    public function isEndTurn(): bool
    {
        return $this->stopReason === self::STOP_REASON_END_TURN;
    }

    public function isMaxTokens(): bool
    {
        return $this->stopReason === self::STOP_REASON_MAX_TOKENS;
    }

    public function isMaxTurnRequests(): bool
    {
        return $this->stopReason === self::STOP_REASON_MAX_TURN_REQUESTS;
    }

    public function isRefusal(): bool
    {
        return $this->stopReason === self::STOP_REASON_REFUSAL;
    }

    public function isCancelled(): bool
    {
        return $this->stopReason === self::STOP_REASON_CANCELLED;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
