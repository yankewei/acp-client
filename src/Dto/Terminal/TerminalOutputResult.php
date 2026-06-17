<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\Terminal;

final class TerminalOutputResult
{
    public function __construct(
        private readonly string $output,
        private readonly bool $truncated,
        private readonly ?TerminalExitStatus $exitStatus = null,
    ) {}

    public function getOutput(): string
    {
        return $this->output;
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function getExitStatus(): ?TerminalExitStatus
    {
        return $this->exitStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function toResultArray(): array
    {
        $result = [
            'output' => $this->output,
            'truncated' => $this->truncated,
        ];

        if ($this->exitStatus !== null) {
            $result['exitStatus'] = $this->exitStatus->toResultArray();
        }

        return $result;
    }
}
