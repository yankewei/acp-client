<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;

final class RequestPermissionOutcome
{
    private function __construct(
        private readonly string $outcome,
        private readonly ?string $optionId = null,
    ) {
    }

    public static function selected(string $optionId): self
    {
        if ($optionId === '') {
            throw new AcpException('Invalid request permission outcome: optionId must be a non-empty string');
        }

        return new self('selected', $optionId);
    }

    public static function cancelled(): self
    {
        return new self('cancelled');
    }

    /**
     * @return array{outcome: array{outcome: string, optionId?: string}}
     */
    public function toResultArray(): array
    {
        $outcome = ['outcome' => $this->outcome];

        if ($this->optionId !== null) {
            $outcome['optionId'] = $this->optionId;
        }

        return ['outcome' => $outcome];
    }
}
