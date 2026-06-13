<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\JsonRpc;

use JsonException;
use Yankewei\AcpClient\Exception\AcpException;

final class Response
{
    private ?Error $error = null;
    private mixed $result = null;

    public function __construct(
        private readonly int|string|null $id,
    ) {
    }

    /**
     * @throws AcpException
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AcpException('Invalid JSON-RPC response: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($data)) {
            throw new AcpException('Invalid JSON-RPC response: expected object');
        }

        if (array_is_list($data)) {
            throw new AcpException('Invalid JSON-RPC response: expected object');
        }

        if (($data['jsonrpc'] ?? null) !== '2.0') {
            throw new AcpException('Invalid JSON-RPC response: missing or invalid jsonrpc version');
        }

        if (!array_key_exists('id', $data)) {
            throw new AcpException('Invalid JSON-RPC response: missing id');
        }

        if (!is_int($data['id']) && !is_string($data['id']) && $data['id'] !== null) {
            throw new AcpException('Invalid JSON-RPC response: id must be a string, integer, or null');
        }

        $hasResult = array_key_exists('result', $data);
        $hasError = array_key_exists('error', $data);

        if ($hasResult === $hasError) {
            throw new AcpException('Invalid JSON-RPC response: must contain exactly one of result or error');
        }

        $response = new self($data['id']);

        if ($hasError) {
            if (!is_array($data['error'])) {
                throw new AcpException('Invalid JSON-RPC response: error must be an object');
            }
            if (array_is_list($data['error'])) {
                throw new AcpException('Invalid JSON-RPC response: error must be an object');
            }
            /** @var array<string, mixed> $errorData */
            $errorData = $data['error'];
            $response->error = Error::fromArray($errorData);
        } else {
            $response->result = $data['result'];
        }

        return $response;
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getError(): ?Error
    {
        return $this->error;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }
}
