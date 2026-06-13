<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\InitializeResult;
use Yankewei\AcpClient\Exception\AcpException;

final class InitializeResultTest extends TestCase
{
    public function testFromArrayParsesFields(): void
    {
        $result = InitializeResult::fromArray([
            'protocolVersion' => 1,
            'agentCapabilities' => ['sessionCapabilities' => ['list' => true]],
        ]);

        self::assertSame(1, $result->getProtocolVersion());
        self::assertSame(['sessionCapabilities' => ['list' => true]], $result->getAgentCapabilities());
    }

    public function testFromArrayAllowsEmptyCapabilities(): void
    {
        $result = InitializeResult::fromArray(['protocolVersion' => 1]);

        self::assertSame(1, $result->getProtocolVersion());
        self::assertSame([], $result->getAgentCapabilities());
    }

    public function testFromArrayRejectsListCapabilities(): void
    {
        $this->expectException(AcpException::class);

        InitializeResult::fromArray(['agentCapabilities' => ['list']]);
    }

    public function testFromArrayRejectsInvalidProtocolVersion(): void
    {
        $this->expectException(AcpException::class);

        InitializeResult::fromArray(['protocolVersion' => '1']);
    }
}
