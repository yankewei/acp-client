<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests;

use Yankewei\AcpClient\Transport\TransportInterface;

final class FakeTransport implements TransportInterface
{
    /** @var string[] */
    public array $sent = [];

    /** @var string[] */
    public array $responses = [];

    public bool $isOpen = false;

    public function open(): void
    {
        $this->isOpen = true;
    }

    public function send(string $message): void
    {
        $this->sent[] = $message;
    }

    public function receive(float $timeout = 0.0): ?string
    {
        return array_shift($this->responses);
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }
}
