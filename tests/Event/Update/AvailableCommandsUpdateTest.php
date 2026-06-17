<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event\Update;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Event\Update\AvailableCommand;
use Yankewei\AcpClient\Event\Update\AvailableCommandInput;
use Yankewei\AcpClient\Event\Update\AvailableCommandsUpdate;
use Yankewei\AcpClient\Event\Update\SessionUpdate;
use Yankewei\AcpClient\Exception\AcpException;

final class AvailableCommandsUpdateTest extends TestCase
{
    public function testParsesAvailableCommands(): void
    {
        $update = AvailableCommandsUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'available_commands_update',
            'availableCommands' => [
                [
                    'name' => 'web',
                    'description' => 'Search the web for information',
                    'input' => [
                        'hint' => 'query to search for',
                    ],
                ],
                [
                    'name' => 'test',
                    'description' => 'Run tests for the current project',
                ],
            ],
        ]);

        static::assertInstanceOf(SessionUpdate::class, $update);
        static::assertSame('sess_1', $update->getSessionId());
        static::assertSame('available_commands_update', $update->getUpdateType());

        $commands = $update->getAvailableCommandObjects();
        static::assertCount(2, $commands);

        static::assertInstanceOf(AvailableCommand::class, $commands[0]);
        static::assertSame('web', $commands[0]->getName());
        static::assertSame('Search the web for information', $commands[0]->getDescription());
        static::assertInstanceOf(AvailableCommandInput::class, $commands[0]->getInput());
        static::assertSame('query to search for', $commands[0]->getInput()?->getHint());

        static::assertInstanceOf(AvailableCommand::class, $commands[1]);
        static::assertSame('test', $commands[1]->getName());
        static::assertSame('Run tests for the current project', $commands[1]->getDescription());
        static::assertNull($commands[1]->getInput());

        static::assertSame(
            [
                [
                    'name' => 'web',
                    'description' => 'Search the web for information',
                    'input' => [
                        'hint' => 'query to search for',
                    ],
                ],
                [
                    'name' => 'test',
                    'description' => 'Run tests for the current project',
                ],
            ],
            $update->getAvailableCommands(),
        );
    }

    public function testParsesEmptyCommandList(): void
    {
        $update = AvailableCommandsUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'available_commands_update',
            'availableCommands' => [],
        ]);

        static::assertSame([], $update->getAvailableCommandObjects());
        static::assertSame([], $update->getAvailableCommands());
    }

    public function testRejectsWrongSessionUpdate(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Invalid available_commands_update update: sessionUpdate must be available_commands_update',
        );

        AvailableCommandsUpdate::fromUpdate('sess_1', ['sessionUpdate' => 'plan']);
    }

    public function testRejectsMissingAvailableCommands(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid available commands update: availableCommands must be a list');

        AvailableCommandsUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'available_commands_update',
        ]);
    }

    public function testRejectsAvailableCommandsNotAList(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid available commands update: availableCommands must be a list');

        AvailableCommandsUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'available_commands_update',
            'availableCommands' => ['web' => ['description' => 'Search the web']],
        ]);
    }

    public function testRejectsCommandNotAnObject(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid available commands update: availableCommands[0] must be an object');

        AvailableCommandsUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'available_commands_update',
            'availableCommands' => ['web'],
        ]);
    }

    public function testRejectsMissingCommandName(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid available command: name must be a string');

        AvailableCommandsUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'available_commands_update',
            'availableCommands' => [
                ['description' => 'Search the web'],
            ],
        ]);
    }

    public function testRejectsMissingCommandDescription(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid available command: description must be a string');

        AvailableCommandsUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'available_commands_update',
            'availableCommands' => [
                ['name' => 'web'],
            ],
        ]);
    }

    public function testRejectsInputNotAnObject(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid available command: input must be an object');

        AvailableCommandsUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'available_commands_update',
            'availableCommands' => [
                [
                    'name' => 'web',
                    'description' => 'Search the web',
                    'input' => 'query',
                ],
            ],
        ]);
    }

    public function testRejectsMissingInputHint(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid available command input: hint must be a string');

        AvailableCommandsUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'available_commands_update',
            'availableCommands' => [
                [
                    'name' => 'web',
                    'description' => 'Search the web',
                    'input' => [],
                ],
            ],
        ]);
    }
}
