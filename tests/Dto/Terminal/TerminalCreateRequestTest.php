<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\Terminal;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\Terminal\TerminalCreateRequest;
use Yankewei\AcpClient\Exception\AcpException;

final class TerminalCreateRequestTest extends TestCase
{
    public function testParsesAllFields(): void
    {
        $request = TerminalCreateRequest::fromArray([
            'sessionId' => 'sess_1',
            'command' => 'npm',
            'args' => ['test', '--coverage'],
            'env' => [
                ['name' => 'NODE_ENV', 'value' => 'test'],
            ],
            'cwd' => '/home/user/project',
            'outputByteLimit' => 1_048_576,
        ]);

        static::assertSame('sess_1', $request->getSessionId());
        static::assertSame('npm', $request->getCommand());
        static::assertSame(['test', '--coverage'], $request->getArgs());
        static::assertSame([['name' => 'NODE_ENV', 'value' => 'test']], $request->getEnv());
        static::assertSame('/home/user/project', $request->getCwd());
        static::assertSame(1_048_576, $request->getOutputByteLimit());
    }

    public function testDefaultsOptionalFields(): void
    {
        $request = TerminalCreateRequest::fromArray([
            'sessionId' => 'sess_1',
            'command' => 'ls',
        ]);

        static::assertSame([], $request->getArgs());
        static::assertSame([], $request->getEnv());
        static::assertNull($request->getCwd());
        static::assertNull($request->getOutputByteLimit());
    }

    public function testRejectsMissingSessionId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid terminal/create params: sessionId must be a string');

        TerminalCreateRequest::fromArray(['command' => 'ls']);
    }

    public function testRejectsMissingCommand(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid terminal/create params: command must be a string');

        TerminalCreateRequest::fromArray(['sessionId' => 'sess_1']);
    }

    public function testRejectsNonListArgs(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid terminal/create params: args must be a list of strings');

        TerminalCreateRequest::fromArray([
            'sessionId' => 'sess_1',
            'command' => 'npm',
            'args' => ['test' => '--coverage'],
        ]);
    }

    public function testRejectsNonStringArgEntry(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid terminal/create params: args must be a list of strings');

        TerminalCreateRequest::fromArray([
            'sessionId' => 'sess_1',
            'command' => 'npm',
            'args' => [123],
        ]);
    }

    public function testRejectsInvalidEnvShape(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid terminal/create params: env must be a list of name/value objects');

        TerminalCreateRequest::fromArray([
            'sessionId' => 'sess_1',
            'command' => 'npm',
            'env' => [['NODE_ENV', 'test']],
        ]);
    }

    public function testRejectsRelativeCwd(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid terminal/create params: cwd must be an absolute path');

        TerminalCreateRequest::fromArray([
            'sessionId' => 'sess_1',
            'command' => 'npm',
            'cwd' => 'project',
        ]);
    }

    public function testRejectsInvalidOutputByteLimit(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid terminal/create params: outputByteLimit must be an integer');

        TerminalCreateRequest::fromArray([
            'sessionId' => 'sess_1',
            'command' => 'npm',
            'outputByteLimit' => '1MB',
        ]);
    }
}
