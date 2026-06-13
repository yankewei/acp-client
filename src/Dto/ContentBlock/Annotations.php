<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\ContentBlock;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class Annotations
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
    ) {
    }

    /**
     * @throws AcpException
     */
    public static function fromArray(mixed $data, string $message): ?self
    {
        if ($data === null) {
            return null;
        }

        /** @var array<string, mixed> $validated */
        $validated = Assert::object($data, $message);

        return new self($validated);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
