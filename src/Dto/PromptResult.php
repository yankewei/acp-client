<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;

final class PromptResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly ?string $stopReason,
        private readonly array $data,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $stopReason = DtoHelper::optionalString($data, 'stopReason');

        return new self($stopReason, $data);
    }

    public function getStopReason(): ?string
    {
        return $this->stopReason;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
