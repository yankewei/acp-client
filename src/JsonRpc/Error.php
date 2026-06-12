<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\JsonRpc;

final class Error
{
    public function __construct(
        private readonly int $code,
        private readonly string $message,
        private readonly mixed $data = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['code'] ?? 0),
            (string) ($data['message'] ?? ''),
            $data['data'] ?? null,
        );
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): mixed
    {
        return $this->data;
    }
}
