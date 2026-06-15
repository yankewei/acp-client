<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class AuthMethod
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly ?string $description = null,
        private readonly string $type = 'agent',
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $id = Assert::requiredString($data, 'id', 'Invalid auth method: id must be a string');
        $name = Assert::requiredString($data, 'name', 'Invalid auth method: name must be a string');
        $description = Assert::optionalString(
            $data,
            'description',
            'Invalid auth method: description must be a string',
        );
        $type = Assert::optionalString($data, 'type', 'Invalid auth method: type must be a string') ?? 'agent';

        if ($type !== 'agent') {
            throw new AcpException('Invalid auth method: type must be agent');
        }

        return new self($id, $name, $description, $type);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
