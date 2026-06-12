<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\JsonRpc;

use Yankewei\AcpClient\Exception\AcpException;

final class Error
{
    public function __construct(
        private readonly int $code,
        private readonly string $message,
        private readonly mixed $data = null,
    ) {}

    /**
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        if (!array_key_exists("code", $data) || !is_int($data["code"])) {
            throw new AcpException(
                "Invalid JSON-RPC response: error.code must be an integer",
            );
        }

        if (
            !array_key_exists("message", $data) ||
            !is_string($data["message"])
        ) {
            throw new AcpException(
                "Invalid JSON-RPC response: error.message must be a string",
            );
        }

        return new self($data["code"], $data["message"], $data["data"] ?? null);
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
