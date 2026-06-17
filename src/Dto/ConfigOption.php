<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class ConfigOption
{
    /**
     * @param ConfigOptionValue[] $options
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $type,
        private readonly string $currentValue,
        private readonly array $options,
        private readonly ?string $description = null,
        private readonly ?string $category = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $options = Assert::list($data['options'] ?? null, 'Invalid config option: options must be a list');

        return new self(
            Assert::requiredString($data, 'id', 'Invalid config option: id must be a string'),
            Assert::requiredString($data, 'name', 'Invalid config option: name must be a string'),
            Assert::requiredString($data, 'type', 'Invalid config option: type must be a string'),
            Assert::requiredString($data, 'currentValue', 'Invalid config option: currentValue must be a string'),
            array_map(
                static fn(mixed $option, int $index): ConfigOptionValue => ConfigOptionValue::fromArray(Assert::object(
                    $option,
                    "Invalid config option: options[{$index}] must be an object",
                )),
                $options,
                array_keys($options),
            ),
            Assert::optionalString($data, 'description', 'Invalid config option: description must be a string or null'),
            Assert::optionalString($data, 'category', 'Invalid config option: category must be a string or null'),
        );
    }

    /**
     * @param mixed $value
     * @return ConfigOption[]
     *
     * @throws AcpException
     */
    public static function listFromArray(mixed $value): array
    {
        $options = Assert::list($value, 'Invalid config options: configOptions must be a list');

        return array_map(
            static fn(mixed $option, int $index): ConfigOption => self::fromArray(Assert::object(
                $option,
                "Invalid config options: configOptions[{$index}] must be an object",
            )),
            $options,
            array_keys($options),
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCurrentValue(): string
    {
        return $this->currentValue;
    }

    /**
     * @return ConfigOptionValue[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function hasValue(string $value): bool
    {
        foreach ($this->options as $option) {
            if ($option->getValue() === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'currentValue' => $this->currentValue,
            'options' => array_map(static fn(ConfigOptionValue $option): array => $option->toArray(), $this->options),
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->category !== null) {
            $data['category'] = $this->category;
        }

        return $data;
    }
}
