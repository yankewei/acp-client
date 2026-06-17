<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Dto\ConfigOption;
use Yankewei\AcpClient\Exception\AcpException;

final class ConfigOptionUpdate implements SessionUpdate
{
    /**
     * @param ConfigOption[] $configOptions
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly array $configOptions,
    ) {}

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'config_option_update') {
            throw new AcpException('Invalid config_option_update update: sessionUpdate must be config_option_update');
        }

        return new self($sessionId, ConfigOption::listFromArray($update['configOptions'] ?? null));
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'config_option_update';
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
