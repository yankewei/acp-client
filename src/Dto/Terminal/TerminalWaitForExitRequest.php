<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\Terminal;

use Yankewei\AcpClient\Util\Assert;

final class TerminalWaitForExitRequest
{
    public function __construct(
        private readonly string $sessionId,
        private readonly string $terminalId,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Assert::requiredString(
                $data,
                'sessionId',
                'Invalid terminal/wait_for_exit params: sessionId must be a string',
            ),
            Assert::requiredString(
                $data,
                'terminalId',
                'Invalid terminal/wait_for_exit params: terminalId must be a string',
            ),
        );
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getTerminalId(): string
    {
        return $this->terminalId;
    }
}
