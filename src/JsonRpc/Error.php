<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\JsonRpc;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class Error
{
    public function __construct(
        private readonly int $code,
        private readonly string $message,
        private readonly mixed $data = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $code = Assert::requiredInt(
            $data,
            "code",
            "Invalid JSON-RPC response: error.code must be an integer",
        );
        $message = Assert::requiredString(
            $data,
            "message",
            "Invalid JSON-RPC response: error.message must be a string",
        );

        return new self($code, $message, $data["data"] ?? null);
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
