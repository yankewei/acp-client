<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class AvailableCommand
{
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly ?AvailableCommandInput $input = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $input = Assert::optionalObjectField($data, 'input', 'Invalid available command: input must be an object');

        return new self(
            Assert::requiredString($data, 'name', 'Invalid available command: name must be a string'),
            Assert::requiredString($data, 'description', 'Invalid available command: description must be a string'),
            $input === null ? null : AvailableCommandInput::fromArray($input),
        );
    }

    /**
     * @param mixed $value
     * @return AvailableCommand[]
     *
     * @throws AcpException
     */
    public static function listFromArray(mixed $value): array
    {
        $commands = Assert::list($value, 'Invalid available commands update: availableCommands must be a list');

        return array_map(
            static fn(mixed $command, int $index): AvailableCommand => self::fromArray(Assert::object(
                $command,
                "Invalid available commands update: availableCommands[{$index}] must be an object",
            )),
            $commands,
            array_keys($commands),
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getInput(): ?AvailableCommandInput
    {
        return $this->input;
    }

    /**
     * @return array{name: string, description: string, input?: array{hint: string}}
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'description' => $this->description,
        ];

        if ($this->input !== null) {
            $data['input'] = $this->input->toArray();
        }

        return $data;
    }
}
