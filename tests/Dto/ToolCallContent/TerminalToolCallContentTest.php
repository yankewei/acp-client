<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\ToolCallContent;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ToolCallContent\TerminalToolCallContent;

final class TerminalToolCallContentTest extends TestCase
{
    public function testGetType(): void
    {
        $terminal = new TerminalToolCallContent('term_xyz789');
        static::assertSame('terminal', $terminal->getType());
    }

    public function testGetTerminalId(): void
    {
        $terminal = new TerminalToolCallContent('term_xyz789');
        static::assertSame('term_xyz789', $terminal->getTerminalId());
    }

    public function testToArray(): void
    {
        $terminal = new TerminalToolCallContent('term_xyz789');

        static::assertSame(['type' => 'terminal', 'terminalId' => 'term_xyz789'], $terminal->toArray());
    }
}
