<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class InitializeResult
{
    /**
     * @param array<string, mixed> $agentCapabilities
     */
    public function __construct(
        private readonly ?int $protocolVersion,
        private readonly array $agentCapabilities,
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

        return new self($protocolVersion, $agentCapabilities);
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
}
