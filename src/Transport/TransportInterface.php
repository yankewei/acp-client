<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Transport;

interface TransportInterface
{
    public function open(): void;

    public function send(string $message): void;

    public function receive(float $timeout = 0.0): ?string;

    public function close(): void;

    public function isOpen(): bool;
}
