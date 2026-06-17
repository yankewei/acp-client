<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\Terminal;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\Terminal\TerminalWaitForExitResult;

final class TerminalWaitForExitResultTest extends TestCase
{
    public function testSerializesToResultArray(): void
    {
        $result = new TerminalWaitForExitResult(0, null);

        static::assertSame(0, $result->getExitCode());
        static::assertNull($result->getSignal());
        static::assertSame(['exitCode' => 0, 'signal' => null], $result->toResultArray());
    }
}
