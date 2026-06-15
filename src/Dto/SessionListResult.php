<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class SessionListResult
{
    /**
     * @param SessionInfo[] $sessionInfos
     */
    public function __construct(
        private readonly array $sessionInfos,
        private readonly ?string $nextCursor,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        if (!array_key_exists('sessions', $data)) {
            throw new AcpException('Invalid session/list result: sessions is required');
        }

        $sessions = Assert::list($data['sessions'], 'Invalid session/list result: sessions must be an array');

        $nextCursor = DtoHelper::optionalString($data, 'nextCursor');

        $sessionInfos = [];
        foreach ($sessions as $session) {
            $sessionInfos[] = SessionInfo::fromArray(Assert::object(
                $session,
                'Invalid session/list result: each session must be an object',
            ));
        }

        return new self($sessionInfos, $nextCursor);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSessions(): array
    {
        return array_values(array_map(
            static fn(SessionInfo $sessionInfo): array => $sessionInfo->toArray(),
            $this->sessionInfos,
        ));
    }

    /**
     * @return SessionInfo[]
     */
    public function getSessionInfos(): array
    {
        return $this->sessionInfos;
    }

    public function getNextCursor(): ?string
    {
        return $this->nextCursor;
    }
}
