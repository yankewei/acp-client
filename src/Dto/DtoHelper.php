<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Util\Assert;

final class DtoHelper
{
    /**
     * @param array<string, mixed> $data
     */
    public static function requireString(array $data, string $key): string
    {
        return Assert::requiredString($data, $key, "Missing or invalid required field: {$key}");
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function requireArray(array $data, string $key): array
    {
        return Assert::requiredObjectField($data, $key, "Missing or invalid required field: {$key}");
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function optionalString(array $data, string $key): ?string
    {
        return Assert::optionalString($data, $key, "Invalid field type: {$key}");
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public static function optionalArray(array $data, string $key): ?array
    {
        return Assert::optionalObjectField($data, $key, "Invalid field type: {$key}");
    }
}
