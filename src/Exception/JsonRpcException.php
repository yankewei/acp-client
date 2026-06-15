<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Exception;

final class JsonRpcException extends AcpException
{
    public const AUTHENTICATION_REQUIRED = -32000;

    public function __construct(
        private readonly int $jsonRpcCode,
        string $message,
        private readonly mixed $data = null,
    ) {
        parent::__construct($message, $jsonRpcCode);
    }

    public function getJsonRpcCode(): int
    {
        return $this->jsonRpcCode;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function isAuthenticationRequired(): bool
    {
        return $this->jsonRpcCode === self::AUTHENTICATION_REQUIRED;
    }
}
