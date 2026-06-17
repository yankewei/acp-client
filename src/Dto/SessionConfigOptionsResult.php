<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;

final class SessionConfigOptionsResult
{
    /**
     * @param ConfigOption[] $configOptions
     */
    public function __construct(
        private readonly array $configOptions,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        return new self(ConfigOption::listFromArray($data['configOptions'] ?? null));
    }

    /**
     * @return ConfigOption[]
     */
    public function getConfigOptionObjects(): array
    {
        return $this->configOptions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getConfigOptions(): array
    {
        return array_values(array_map(
            static fn(ConfigOption $option): array => $option->toArray(),
            $this->configOptions,
        ));
    }
}
