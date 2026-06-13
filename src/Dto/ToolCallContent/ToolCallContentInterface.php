<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ToolCallContent;

interface ToolCallContentInterface
{
    public function getType(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}