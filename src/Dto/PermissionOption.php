<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class PermissionOption
{
    /** @var string[] */
    private const KINDS = ['allow_once', 'allow_always', 'reject_once', 'reject_always'];

    public function __construct(
        private readonly string $optionId,
        private readonly string $name,
        private readonly string $kind,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Assert::requiredString(
                $data,
                'optionId',
                'Invalid permission option: optionId must be a string',
            ),
            Assert::requiredString(
                $data,
                'name',
                'Invalid permission option: name must be a string',
            ),
            Assert::optionalStringInEnum(
                $data['kind'] ?? null,
                self::KINDS,
                'Invalid permission option: kind must be one of allow_once, allow_always, reject_once, reject_always',
            ) ?? throw new AcpException('Invalid permission option: kind must be one of allow_once, allow_always, reject_once, reject_always'),
        );
    }

    public function getOptionId(): string
    {
        return $this->optionId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKind(): string
    {
        return $this->kind;
    }
}
