<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;

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
        $protocolVersion = $data['protocolVersion'] ?? null;
        if ($protocolVersion !== null && !is_int($protocolVersion)) {
            throw new AcpException('Invalid initialize result: protocolVersion must be an integer');
        }

        $agentCapabilities = $data['agentCapabilities'] ?? [];
        if (!is_array($agentCapabilities) || ($agentCapabilities !== [] && array_is_list($agentCapabilities))) {
            throw new AcpException('Invalid initialize result: agentCapabilities must be an object');
        }

        /** @var array<string, mixed> $agentCapabilities */
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
