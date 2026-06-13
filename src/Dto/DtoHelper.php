<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;

final class DtoHelper
{
    /**
     * @param array<string, mixed> $data
     */
    public static function requireString(array $data, string $key): string
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key])) {
            throw new AcpException("Missing or invalid required field: {$key}");
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function requireArray(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key]) || array_is_list($data[$key])) {
            throw new AcpException("Missing or invalid required field: {$key}");
        }

        /** @var array<string, mixed> $value */
        $value = $data[$key];

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function optionalString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        if (!is_string($data[$key])) {
            throw new AcpException("Invalid field type: {$key}");
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public static function optionalArray(array $data, string $key): ?array
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        if (!is_array($data[$key]) || array_is_list($data[$key])) {
            throw new AcpException("Invalid field type: {$key}");
        }

        /** @var array<string, mixed> $value */
        $value = $data[$key];

        return $value;
    }
}
