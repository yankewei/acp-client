<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

final class Session
{
    /**
     * @param ConfigOption[] $configOptions
     */
    public function __construct(
        private readonly ?string $sessionId,
        private readonly array $configOptions = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $sessionId = DtoHelper::optionalString($data, 'sessionId');

        $configOptions = array_key_exists('configOptions', $data)
            ? ConfigOption::listFromArray($data['configOptions'])
            : [];

        return new self($sessionId, $configOptions);
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
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

    /**
     * @return ConfigOption[]
     */
    public function getConfigOptionObjects(): array
    {
        return $this->configOptions;
    }

    public function getConfigOption(string $id): ?ConfigOption
    {
        foreach ($this->configOptions as $option) {
            if ($option->getId() === $id) {
                return $option;
            }
        }

        return null;
    }
}
