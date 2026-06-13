<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;

final class Session
{
    /**
     * @param array<int, array<string, mixed>> $configOptions
     */
    public function __construct(
        private readonly ?string $sessionId,
        private readonly array $configOptions = [],
    ) {
    }

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

        /** @var array<int, array<string, mixed>> $configOptions */
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
