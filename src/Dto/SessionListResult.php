<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;

final class SessionListResult
{
    /**
     * @param array<int, array<string, mixed>> $sessions
     */
    public function __construct(
        private readonly array $sessions,
        private readonly ?string $nextCursor,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $sessions = $data['sessions'] ?? [];
        if (!is_array($sessions) || !array_is_list($sessions)) {
            throw new AcpException('Invalid session/list result: sessions must be an array');
        }

        /** @var array<int, array<string, mixed>> $sessions */
        foreach ($sessions as $session) {
            /** @var mixed $session */
            if (!is_array($session) || array_is_list($session)) {
                throw new AcpException('Invalid session/list result: each session must be an object');
            }
        }

        $nextCursor = DtoHelper::optionalString($data, 'nextCursor');

        return new self($sessions, $nextCursor);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSessions(): array
    {
        return $this->sessions;
    }

    public function getNextCursor(): ?string
    {
        return $this->nextCursor;
    }
}
