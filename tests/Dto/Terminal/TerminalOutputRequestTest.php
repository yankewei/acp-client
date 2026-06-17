<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\Terminal;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\Terminal\TerminalOutputRequest;
use Yankewei\AcpClient\Exception\AcpException;

final class TerminalOutputRequestTest extends TestCase
{
    public function testParsesFields(): void
    {
        $request = TerminalOutputRequest::fromArray([
            'sessionId' => 'sess_1',
            'terminalId' => 'term_xyz789',
        ]);

        static::assertSame('sess_1', $request->getSessionId());
        static::assertSame('term_xyz789', $request->getTerminalId());
    }

    public function testRejectsMissingSessionId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid terminal/output params: sessionId must be a string');

        TerminalOutputRequest::fromArray(['terminalId' => 'term_xyz789']);
    }

    public function testRejectsMissingTerminalId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid terminal/output params: terminalId must be a string');

        TerminalOutputRequest::fromArray(['sessionId' => 'sess_1']);
    }
}
