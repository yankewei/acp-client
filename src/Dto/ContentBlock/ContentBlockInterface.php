<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ContentBlock;

interface ContentBlockInterface
{
    /**
     * Returns the content block type, e.g. "text", "image", "audio",
     * "resource", or "resource_link".
     */
    public function getType(): string;

    /**
     * Returns the content block as an associative array in ACP/MCP shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
