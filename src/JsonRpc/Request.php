<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\JsonRpc;

use JsonException;

final class Request
{
    private static int $idCounter = 0;

    public function __construct(
        private readonly string $method,
        private readonly int|string $id,
        private readonly array $params = [],
    ) {
    }

    public static function nextId(): int
    {
        return ++self::$idCounter;
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode([
            'jsonrpc' => '2.0',
            'id' => $this->id,
            'method' => $this->method,
            'params' => $this->params,
        ], JSON_THROW_ON_ERROR);
    }
}
