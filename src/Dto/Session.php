<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

final class Session
{
    /**
     * @param array<int, array<string, mixed>> $configOptions
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

        $configOptions = $data['configOptions'] ?? [];
        if (!is_array($configOptions) || !array_is_list($configOptions)) {
            $configOptions = [];
        }

        $configOptions = array_map(
            static function (mixed $option): array {
                if (!is_array($option) || array_is_list($option)) {
                    return [];
                }

                /** @var array<string, mixed> $option */
                return $option;
            },
            $configOptions,
        );

        return new self($sessionId, $configOptions);
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getConfigOptions(): array
    {
        return $this->configOptions;
    }
}
