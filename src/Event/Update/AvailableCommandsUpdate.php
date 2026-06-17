<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Exception\AcpException;

final class AvailableCommandsUpdate implements SessionUpdate
{
    /**
     * @param AvailableCommand[] $availableCommands
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly array $availableCommands,
    ) {}

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'available_commands_update') {
            throw new AcpException(
                'Invalid available_commands_update update: sessionUpdate must be available_commands_update',
            );
        }

        return new self($sessionId, AvailableCommand::listFromArray($update['availableCommands'] ?? null));
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'available_commands_update';
    }

    /**
     * @return AvailableCommand[]
     */
    public function getAvailableCommandObjects(): array
    {
        return $this->availableCommands;
    }

    /**
     * @return list<array{name: string, description: string, input?: array{hint: string}}>
     */
    public function getAvailableCommands(): array
    {
        return array_values(array_map(
            static fn(AvailableCommand $command): array => $command->toArray(),
            $this->availableCommands,
        ));
    }
}
