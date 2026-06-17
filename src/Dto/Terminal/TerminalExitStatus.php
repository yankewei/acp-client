<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\Terminal;

final class TerminalExitStatus
{
    public function __construct(
        private readonly ?int $exitCode,
        private readonly ?string $signal,
    ) {}

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    public function getSignal(): ?string
    {
        return $this->signal;
    }

    /**
     * @return array{exitCode: int|null, signal: string|null}
     */
    public function toResultArray(): array
    {
        return [
            'exitCode' => $this->exitCode,
            'signal' => $this->signal,
        ];
    }
}
