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

        static::assertSame(1, $result->getProtocolVersion());
        static::assertSame(
            [
                'sessionCapabilities' => ['list' => true],
                'auth' => ['logout' => []],
            ],
            $result->getAgentCapabilities(),
        );
        static::assertTrue($result->supportsLogout());

        $authMethods = $result->getAuthMethods();
        static::assertCount(1, $authMethods);
        static::assertSame('agent-login', $authMethods[0]->getId());
        static::assertSame('Agent login', $authMethods[0]->getName());
        static::assertSame('Sign in using the agent', $authMethods[0]->getDescription());
        static::assertSame('agent', $authMethods[0]->getType());
        static::assertSame($authMethods[0], $result->getAuthMethod('agent-login'));
        static::assertNull($result->getAuthMethod('missing'));
    }

    public function testFromArrayAllowsEmptyCapabilities(): void
    {
        $result = InitializeResult::fromArray(['protocolVersion' => 1]);

        static::assertSame(1, $result->getProtocolVersion());
        static::assertSame([], $result->getAgentCapabilities());
        static::assertSame([], $result->getAuthMethods());
        static::assertFalse($result->supportsLogout());
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

    public function testFromArrayRejectsInvalidAuthMethodType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid auth method: type must be agent');

        InitializeResult::fromArray([
            'authMethods' => [
                [
                    'id' => 'login',
                    'name' => 'Login',
                    'type' => '',
                ],
            ],
        ]);
    }

    public function testSupportsLogoutRequiresObjectCapability(): void
    {
        static::assertFalse(
            InitializeResult::fromArray([
                'agentCapabilities' => ['auth' => ['logout' => null]],
            ])->supportsLogout(),
        );

        static::assertTrue(
            InitializeResult::fromArray([
                'agentCapabilities' => ['auth' => ['logout' => []]],
            ])->supportsLogout(),
        );

        static::assertTrue(
            InitializeResult::fromArray([
                'agentCapabilities' => ['auth' => ['logout' => ['_meta' => []]]],
            ])->supportsLogout(),
        );
    }

    public function testCapabilityHelpers(): void
    {
        $result = InitializeResult::fromArray([
            'agentCapabilities' => [
                'loadSession' => true,
                'sessionCapabilities' => [
                    'list' => [],
                    'resume' => [],
                    'close' => [],
                    'delete' => [],
                    'additionalDirectories' => [],
                ],
                'mcpCapabilities' => [
                    'http' => true,
                    'sse' => true,
                ],
                'promptCapabilities' => [
                    'image' => true,
                    'audio' => true,
                    'embeddedContext' => true,
                ],
            ],
        ]);

        static::assertTrue($result->supportsLoadSession());
        static::assertTrue($result->supportsSessionList());
        static::assertTrue($result->supportsSessionResume());
        static::assertTrue($result->supportsSessionClose());
        static::assertTrue($result->supportsSessionDelete());
        static::assertTrue($result->supportsAdditionalDirectories());
        static::assertTrue($result->supportsMcpHttp());
        static::assertTrue($result->supportsMcpSse());
        static::assertTrue($result->supportsPromptImage());
        static::assertTrue($result->supportsPromptAudio());
        static::assertTrue($result->supportsPromptEmbeddedContext());
    }

    public function testPromptCapabilitiesRequireBooleanValues(): void
    {
        $result = InitializeResult::fromArray([
            'agentCapabilities' => [
                'promptCapabilities' => [
                    'image' => true,
                    'audio' => true,
                    'embeddedContext' => true,
                ],
            ],
        ]);

        static::assertTrue($result->supportsPromptImage());
        static::assertTrue($result->supportsPromptAudio());
        static::assertTrue($result->supportsPromptEmbeddedContext());

        $result = InitializeResult::fromArray([
            'agentCapabilities' => [
                'promptCapabilities' => [
                    'image' => [],
                    'audio' => [],
                    'embeddedContext' => [],
                ],
            ],
        ]);

        static::assertFalse($result->supportsPromptImage());
        static::assertFalse($result->supportsPromptAudio());
        static::assertFalse($result->supportsPromptEmbeddedContext());
    }
}
