<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Util;

final class Path
{
    private function __construct()
    {
    }

    public static function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return true;
        }

        return str_starts_with($path, '\\\\');
    }
}
