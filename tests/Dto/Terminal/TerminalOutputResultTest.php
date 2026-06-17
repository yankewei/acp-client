<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\Terminal;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\Terminal\TerminalExitStatus;
use Yankewei\AcpClient\Dto\Terminal\TerminalOutputResult;

final class TerminalOutputResultTest extends TestCase
{
    public function testSerializesWithoutExitStatus(): void
    {
        $result = new TerminalOutputResult('Running tests...', false);

        static::assertSame('Running tests...', $result->getOutput());
        static::assertFalse($result->isTruncated());
        static::assertNull($result->getExitStatus());
        static::assertSame(
            [
                'output' => 'Running tests...',
                'truncated' => false,
            ],
            $result->toResultArray(),
        );
    }

    public function testSerializesWithExitStatus(): void
    {
        $result = new TerminalOutputResult('done', true, new TerminalExitStatus(0, null));

        static::assertSame(
            [
                'output' => 'done',
                'truncated' => true,
                'exitStatus' => [
                    'exitCode' => 0,
                    'signal' => null,
                ],
            ],
            $result->toResultArray(),
        );
    }
}
