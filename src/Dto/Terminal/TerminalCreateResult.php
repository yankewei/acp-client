<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\Terminal;

final class TerminalCreateResult
{
    public function __construct(
        private readonly string $terminalId,
    ) {}

    public static function fromTerminalId(string $terminalId): self
    {
        return new self($terminalId);
    }

    public function getTerminalId(): string
    {
        return $this->terminalId;
    }

    /**
     * @return array{terminalId: string}
     */
    public function toResultArray(): array
    {
        return ['terminalId' => $this->terminalId];
    }
}
