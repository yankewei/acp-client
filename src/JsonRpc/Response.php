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
        private readonly int|string $id,
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

        if (!array_key_exists('id', $data)) {
            throw new AcpException('Invalid JSON-RPC response: missing id');
        }

        $response = new self($data['id']);

        if (array_key_exists('error', $data) && $data['error'] !== null) {
            if (!is_array($data['error'])) {
                throw new AcpException('Invalid JSON-RPC response: error must be an object');
            }
            $response->error = Error::fromArray($data['error']);
        } elseif (array_key_exists('result', $data)) {
            $response->result = $data['result'];
        }

        return $response;
    }

    public function getId(): int|string
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
