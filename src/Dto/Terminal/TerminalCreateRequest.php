<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\Terminal;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;
use Yankewei\AcpClient\Util\Path;

final class TerminalCreateRequest
{
    /**
     * @param array<int, array{name: string, value: string}> $env
     * @param string[] $args
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly string $command,
        private readonly array $args = [],
        private readonly array $env = [],
        private readonly ?string $cwd = null,
        private readonly ?int $outputByteLimit = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $command = Assert::requiredString($data, 'command', 'Invalid terminal/create params: command must be a string');

        $cwd = Assert::optionalString($data, 'cwd', 'Invalid terminal/create params: cwd must be a string or null');
        if ($cwd !== null && !Path::isAbsolutePath($cwd)) {
            throw new AcpException('Invalid terminal/create params: cwd must be an absolute path');
        }

        $args = self::parseArgs($data['args'] ?? null);
        $env = self::parseEnv($data['env'] ?? null);

        $outputByteLimit = Assert::optionalInt(
            $data['outputByteLimit'] ?? null,
            'Invalid terminal/create params: outputByteLimit must be an integer',
        );

        return new self(
            Assert::requiredString($data, 'sessionId', 'Invalid terminal/create params: sessionId must be a string'),
            $command,
            $args,
            $env,
            $cwd,
            $outputByteLimit,
        );
    }

    /**
     * @return string[]
     *
     * @throws AcpException
     */
    private static function parseArgs(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new AcpException('Invalid terminal/create params: args must be a list of strings');
        }

        foreach ($value as $index => $item) {
            if (!is_string($item)) {
                throw new AcpException('Invalid terminal/create params: args must be a list of strings');
            }
        }

        /** @var string[] $value */
        return $value;
    }

    /**
     * @return array<int, array{name: string, value: string}>
     *
     * @throws AcpException
     */
    private static function parseEnv(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new AcpException('Invalid terminal/create params: env must be a list of name/value objects');
        }

        $env = [];
        foreach ($value as $index => $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                throw new AcpException('Invalid terminal/create params: env must be a list of name/value objects');
            }

            $name = $entry['name'] ?? null;
            $envValue = $entry['value'] ?? null;
            if (!is_string($name) || !is_string($envValue)) {
                throw new AcpException('Invalid terminal/create params: env must be a list of name/value objects');
            }

            $env[] = ['name' => $name, 'value' => $envValue];
        }

        return $env;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @return string[]
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    public function getEnv(): array
    {
        return $this->env;
    }

    public function getCwd(): ?string
    {
        return $this->cwd;
    }

    public function getOutputByteLimit(): ?int
    {
        return $this->outputByteLimit;
    }
}
