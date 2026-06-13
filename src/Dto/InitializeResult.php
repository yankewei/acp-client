<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class InitializeResult
{
    /**
     * @param array<string, mixed> $agentCapabilities
     * @param AuthMethod[] $authMethods
     */
    public function __construct(
        private readonly ?int $protocolVersion,
        private readonly array $agentCapabilities,
        private readonly array $authMethods = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $protocolVersion = Assert::optionalInt(
            $data['protocolVersion'] ?? null,
            'Invalid initialize result: protocolVersion must be an integer',
        );

        $agentCapabilities = $data['agentCapabilities'] ?? [];
        $agentCapabilities = Assert::object(
            $agentCapabilities,
            'Invalid initialize result: agentCapabilities must be an object',
        );

        $authMethods = $data['authMethods'] ?? [];
        $authMethods = Assert::list(
            $authMethods,
            'Invalid initialize result: authMethods must be a list',
        );

        return new self(
            $protocolVersion,
            $agentCapabilities,
            array_map(
                static function (mixed $authMethod): AuthMethod {
                    return AuthMethod::fromArray(
                        Assert::object($authMethod, 'Invalid initialize result: authMethods entries must be objects'),
                    );
                },
                $authMethods,
            ),
        );
    }

    public function getProtocolVersion(): ?int
    {
        return $this->protocolVersion;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAgentCapabilities(): array
    {
        return $this->agentCapabilities;
    }

    /**
     * @return AuthMethod[]
     */
    public function getAuthMethods(): array
    {
        return $this->authMethods;
    }

    public function getAuthMethod(string $id): ?AuthMethod
    {
        foreach ($this->authMethods as $authMethod) {
            if ($authMethod->getId() === $id) {
                return $authMethod;
            }
        }

        return null;
    }

    public function supportsLogout(): bool
    {
        $auth = $this->agentCapabilities['auth'] ?? null;
        if (!is_array($auth) || array_is_list($auth)) {
            return false;
        }

        if (!array_key_exists('logout', $auth)) {
            return false;
        }

        $logout = $auth['logout'];

        return is_array($logout) && ($logout === [] || !array_is_list($logout));
    }
}
