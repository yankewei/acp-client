<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\JsonRpc;

use JsonException;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

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
        $data = self::decodeObject($json);

        self::assertJsonRpcVersion($data);
        self::assertSinglePayload($data);

        $id = self::responseId($data);

        return array_key_exists('error', $data)
            ? self::fromError($id, Assert::requiredObjectField($data, 'error', 'Invalid JSON-RPC response: error must be an object'))
            : self::fromResult($id, $data['result']);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AcpException
     */
    private static function decodeObject(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AcpException('Invalid JSON-RPC response: ' . $e->getMessage(), 0, $e);
        }

        return Assert::object($data, 'Invalid JSON-RPC response: expected object');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    private static function assertJsonRpcVersion(array $data): void
    {
        if (($data['jsonrpc'] ?? null) !== '2.0') {
            throw new AcpException('Invalid JSON-RPC response: missing or invalid jsonrpc version');
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    private static function responseId(array $data): int|string|null
    {
        if (!array_key_exists('id', $data)) {
            throw new AcpException('Invalid JSON-RPC response: missing id');
        }

        return Assert::jsonRpcId($data['id'], 'Invalid JSON-RPC response: id must be a string, integer, or null');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    private static function assertSinglePayload(array $data): void
    {
        $hasResult = array_key_exists('result', $data);
        $hasError = array_key_exists('error', $data);

        if ($hasResult === $hasError) {
            throw new AcpException('Invalid JSON-RPC response: must contain exactly one of result or error');
        }
    }

    private static function fromResult(int|string|null $id, mixed $result): self
    {
        $response = new self($id);
        $response->result = $result;

        return $response;
    }

    /**
     * @param array<string, mixed> $errorData
     *
     * @throws AcpException
     */
    private static function fromError(int|string|null $id, array $errorData): self
    {
        $response = new self($id);
        $response->error = Error::fromArray($errorData);

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
