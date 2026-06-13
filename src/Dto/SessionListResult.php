<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

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
        $sessions = Assert::list(
            $data['sessions'] ?? [],
            'Invalid session/list result: sessions must be an array',
        );

        foreach ($sessions as $index => $session) {
            $sessions[$index] = Assert::object(
                $session,
                'Invalid session/list result: each session must be an object',
            );
        }

        $nextCursor = DtoHelper::optionalString($data, 'nextCursor');

        /** @var array<int, array<string, mixed>> $sessions */
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
