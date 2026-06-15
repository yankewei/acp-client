<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Util;

use Yankewei\AcpClient\Exception\AcpException;

final class Assert
{
    private function __construct() {}

    /**
     * @return array<string, mixed>
     *
     * @throws AcpException
     */
    public static function object(mixed $value, string $message): array
    {
        if (!is_array($value) || $value !== [] && array_is_list($value)) {
            throw new AcpException($message);
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @return array<int, mixed>
     *
     * @throws AcpException
     */
    public static function list(mixed $value, string $message): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new AcpException($message);
        }

        /** @var array<int, mixed> $value */
        return $value;
    }

    /**
     * @return array<int, mixed>
     *
     * @throws AcpException
     */
    public static function optionalList(mixed $value, string $message): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new AcpException($message);
        }

        /** @var array<int, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function requiredString(array $data, string $key, string $message): string
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key])) {
            throw new AcpException($message);
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function optionalString(array $data, string $key, string $message): ?string
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        if (!is_string($data[$key])) {
            throw new AcpException($message);
        }

        return $data[$key];
    }

    /**
     * @param string[] $allowed
     *
     * @throws AcpException
     */
    public static function optionalStringInEnum(mixed $value, array $allowed, string $message): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new AcpException($message);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function requiredInt(array $data, string $key, string $message): int
    {
        if (!array_key_exists($key, $data) || !is_int($data[$key])) {
            throw new AcpException($message);
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     *
     * @throws AcpException
     */
    public static function requiredObjectField(array $data, string $key, string $message): array
    {
        if (!array_key_exists($key, $data)) {
            throw new AcpException($message);
        }

        return self::object($data[$key], $message);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     *
     * @throws AcpException
     */
    public static function optionalObjectField(array $data, string $key, string $message): ?array
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        return self::object($data[$key], $message);
    }

    /**
     * @throws AcpException
     */
    public static function optionalInt(mixed $value, string $message): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!is_int($value)) {
            throw new AcpException($message);
        }

        return $value;
    }

    /**
     * @throws AcpException
     */
    public static function jsonRpcId(mixed $value, string $message): int|string|null
    {
        if (!is_int($value) && !is_string($value) && $value !== null) {
            throw new AcpException($message);
        }

        return $value;
    }
}
