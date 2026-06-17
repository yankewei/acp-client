<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\Terminal;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\Terminal\TerminalCreateResult;

final class TerminalCreateResultTest extends TestCase
{
    public function testSerializesToResultArray(): void
    {
        $result = new TerminalCreateResult('term_xyz789');

        static::assertSame('term_xyz789', $result->getTerminalId());
        static::assertSame(['terminalId' => 'term_xyz789'], $result->toResultArray());
    }

    public function testFromTerminalIdFactory(): void
    {
        $result = TerminalCreateResult::fromTerminalId('term_abc');

        static::assertSame('term_abc', $result->getTerminalId());
    }
}
