<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event;

final class Notification
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        private readonly string $method,
        private readonly array $params,
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function is(string $method): bool
    {
        return $this->method === $method;
    }
}
