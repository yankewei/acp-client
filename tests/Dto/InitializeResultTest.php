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
            'agentCapabilities' => [
                'sessionCapabilities' => ['list' => true],
                'auth' => ['logout' => []],
            ],
            'authMethods' => [
                [
                    'id' => 'agent-login',
                    'name' => 'Agent login',
                    'description' => 'Sign in using the agent',
                ],
            ],
        ]);

        self::assertSame(1, $result->getProtocolVersion());
        self::assertSame(
            [
                'sessionCapabilities' => ['list' => true],
                'auth' => ['logout' => []],
            ],
            $result->getAgentCapabilities(),
        );
        self::assertTrue($result->supportsLogout());

        $authMethods = $result->getAuthMethods();
        self::assertCount(1, $authMethods);
        self::assertSame('agent-login', $authMethods[0]->getId());
        self::assertSame('Agent login', $authMethods[0]->getName());
        self::assertSame('Sign in using the agent', $authMethods[0]->getDescription());
        self::assertSame('agent', $authMethods[0]->getType());
        self::assertSame($authMethods[0], $result->getAuthMethod('agent-login'));
        self::assertNull($result->getAuthMethod('missing'));
    }

    public function testFromArrayAllowsEmptyCapabilities(): void
    {
        $result = InitializeResult::fromArray(['protocolVersion' => 1]);

        self::assertSame(1, $result->getProtocolVersion());
        self::assertSame([], $result->getAgentCapabilities());
        self::assertSame([], $result->getAuthMethods());
        self::assertFalse($result->supportsLogout());
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

    public function testFromArrayRejectsInvalidAuthMethods(): void
    {
        $this->expectException(AcpException::class);

        InitializeResult::fromArray(['authMethods' => ['login' => []]]);
    }

    public function testFromArrayRejectsInvalidAuthMethodEntry(): void
    {
        $this->expectException(AcpException::class);

        InitializeResult::fromArray(['authMethods' => [['id' => 'login']]]);
    }

    public function testSupportsLogoutRequiresObjectCapability(): void
    {
        self::assertFalse(InitializeResult::fromArray([
            'agentCapabilities' => ['auth' => ['logout' => null]],
        ])->supportsLogout());

        self::assertTrue(InitializeResult::fromArray([
            'agentCapabilities' => ['auth' => ['logout' => []]],
        ])->supportsLogout());

        self::assertTrue(InitializeResult::fromArray([
            'agentCapabilities' => ['auth' => ['logout' => ['_meta' => []]]],
        ])->supportsLogout());
    }

    public function testCapabilityHelpers(): void
    {
        $result = InitializeResult::fromArray([
            'agentCapabilities' => [
                'loadSession' => true,
                'sessionCapabilities' => [
                    'resume' => [],
                    'close' => [],
                    'additionalDirectories' => [],
                ],
                'mcpCapabilities' => [
                    'http' => true,
                    'sse' => true,
                ],
            ],
        ]);

        self::assertTrue($result->supportsLoadSession());
        self::assertTrue($result->supportsSessionResume());
        self::assertTrue($result->supportsSessionClose());
        self::assertTrue($result->supportsAdditionalDirectories());
        self::assertTrue($result->supportsMcpHttp());
        self::assertTrue($result->supportsMcpSse());
    }
}
