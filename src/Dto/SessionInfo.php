<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;
use Yankewei\AcpClient\Util\Path;

final class SessionInfo
{
    /**
     * @param string[] $additionalDirectories
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly string $cwd,
        private readonly array $additionalDirectories = [],
        private readonly ?string $title = null,
        private readonly ?string $updatedAt = null,
        private readonly array $meta = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $sessionId = Assert::requiredString(
            $data,
            'sessionId',
            'Invalid session info: sessionId must be a string',
        );
        $cwd = Assert::requiredString(
            $data,
            'cwd',
            'Invalid session info: cwd must be a string',
        );

        if (!Path::isAbsolutePath($cwd)) {
            throw new AcpException('Invalid session info: cwd must be an absolute path');
        }

        $additionalDirectories = self::additionalDirectories($data['additionalDirectories'] ?? []);
        $title = DtoHelper::optionalString($data, 'title');
        $updatedAt = DtoHelper::optionalString($data, 'updatedAt');
        $meta = Assert::object(
            $data['_meta'] ?? [],
            'Invalid session info: _meta must be an object',
        );

        return new self($sessionId, $cwd, $additionalDirectories, $title, $updatedAt, $meta);
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getCwd(): string
    {
        return $this->cwd;
    }

    /**
     * @return string[]
     */
    public function getAdditionalDirectories(): array
    {
        return $this->additionalDirectories;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @return string[]
     *
     * @throws AcpException
     */
    private static function additionalDirectories(mixed $value): array
    {
        $directories = Assert::list(
            $value,
            'Invalid session info: additionalDirectories must be a list of strings',
        );

        foreach ($directories as $directory) {
            if (!is_string($directory)) {
                throw new AcpException('Invalid session info: additionalDirectories must be a list of strings');
            }

            if (!Path::isAbsolutePath($directory)) {
                throw new AcpException('Invalid session info: additionalDirectories entries must be absolute paths');
            }
        }

        /** @var string[] $directories */
        return $directories;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'sessionId' => $this->sessionId,
            'cwd' => $this->cwd,
            'additionalDirectories' => $this->additionalDirectories,
        ];

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->updatedAt !== null) {
            $data['updatedAt'] = $this->updatedAt;
        }

        if ($this->meta !== []) {
            $data['_meta'] = $this->meta;
        }

        return $data;
    }
}
