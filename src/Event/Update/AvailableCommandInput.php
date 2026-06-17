<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class AvailableCommandInput
{
    public function __construct(
        private readonly string $hint,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        return new self(Assert::requiredString(
            $data,
            'hint',
            'Invalid available command input: hint must be a string',
        ));
    }

    public function getHint(): string
    {
        return $this->hint;
    }

    /**
     * @return array{hint: string}
     */
    public function toArray(): array
    {
        return ['hint' => $this->hint];
    }
}
