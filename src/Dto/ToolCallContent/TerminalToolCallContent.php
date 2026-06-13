<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ToolCallContent;

final class TerminalToolCallContent implements ToolCallContentInterface
{
    public function __construct(
        private readonly string $terminalId,
    ) {
    }

    public function getType(): string
    {
        return 'terminal';
    }

    public function getTerminalId(): string
    {
        return $this->terminalId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'terminal',
            'terminalId' => $this->terminalId,
        ];
    }
}