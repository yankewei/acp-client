<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\Terminal;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\Terminal\TerminalExitStatus;

final class TerminalExitStatusTest extends TestCase
{
    public function testSerializesToResultArray(): void
    {
        $status = new TerminalExitStatus(0, null);

        static::assertSame(0, $status->getExitCode());
        static::assertNull($status->getSignal());
        static::assertSame(['exitCode' => 0, 'signal' => null], $status->toResultArray());
    }

    public function testSerializesSignalExit(): void
    {
        $status = new TerminalExitStatus(null, 'SIGTERM');

        static::assertNull($status->getExitCode());
        static::assertSame('SIGTERM', $status->getSignal());
        static::assertSame(['exitCode' => null, 'signal' => 'SIGTERM'], $status->toResultArray());
    }
}
