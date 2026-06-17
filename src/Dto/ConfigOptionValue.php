<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class ConfigOptionValue
{
    public function __construct(
        private readonly string $value,
        private readonly string $name,
        private readonly ?string $description = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Assert::requiredString($data, 'value', 'Invalid config option value: value must be a string'),
            Assert::requiredString($data, 'name', 'Invalid config option value: name must be a string'),
            Assert::optionalString(
                $data,
                'description',
                'Invalid config option value: description must be a string or null',
            ),
        );
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'value' => $this->value,
            'name' => $this->name,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }
}
