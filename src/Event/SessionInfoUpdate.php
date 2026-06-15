<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event;

use Yankewei\AcpClient\Event\Update\SessionUpdate;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class SessionInfoUpdate implements SessionUpdate
{
    /**
     * @param array<string, mixed>|null $meta
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly bool $hasTitle,
        private readonly ?string $title,
        private readonly bool $hasUpdatedAt,
        private readonly ?string $updatedAt,
        private readonly bool $hasMeta,
        private readonly ?array $meta,
    ) {}

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'session_info_update') {
            throw new AcpException('Invalid session info update: sessionUpdate must be session_info_update');
        }

        $hasTitle = array_key_exists('title', $update);
        $title = self::nullableString($update, 'title', 'Invalid session info update: title must be a string or null');

        $hasUpdatedAt = array_key_exists('updatedAt', $update);
        $updatedAt = self::nullableString(
            $update,
            'updatedAt',
            'Invalid session info update: updatedAt must be a string or null',
        );

        $hasMeta = array_key_exists('_meta', $update);
        $meta = null;
        if ($hasMeta && $update['_meta'] !== null) {
            $meta = Assert::object($update['_meta'], 'Invalid session info update: _meta must be an object or null');
        }

        return new self($sessionId, $hasTitle, $title, $hasUpdatedAt, $updatedAt, $hasMeta, $meta);
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'session_info_update';
    }

    public function hasTitle(): bool
    {
        return $this->hasTitle;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function hasUpdatedAt(): bool
    {
        return $this->hasUpdatedAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function hasMeta(): bool
    {
        return $this->hasMeta;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    private static function nullableString(array $data, string $key, string $message): ?string
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        if ($data[$key] !== null && !is_string($data[$key])) {
            throw new AcpException($message);
        }

        return $data[$key];
    }
}
