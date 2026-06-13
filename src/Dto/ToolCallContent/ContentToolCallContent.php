<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ToolCallContent;

use Yankewei\AcpClient\Dto\ContentBlock\ContentBlockInterface;

final class ContentToolCallContent implements ToolCallContentInterface
{
    public function __construct(
        private readonly ContentBlockInterface $content,
    ) {
    }

    public function getType(): string
    {
        return 'content';
    }

    public function getContentBlock(): ContentBlockInterface
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'content',
            'content' => $this->content->toArray(),
        ];
    }
}