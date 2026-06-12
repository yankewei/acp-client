<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Exception;

class JsonRpcException extends AcpException
{
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
}
