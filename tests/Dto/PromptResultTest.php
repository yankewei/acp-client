<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\PromptResult;

final class PromptResultTest extends TestCase
{
    public function testFromArrayParsesStopReason(): void
    {
        $result = PromptResult::fromArray(['stopReason' => 'end_turn']);

        self::assertSame('end_turn', $result->getStopReason());
        self::assertSame(['stopReason' => 'end_turn'], $result->getData());
    }

    public function testFromArrayDefaults(): void
    {
        $result = PromptResult::fromArray([]);

        self::assertNull($result->getStopReason());
        self::assertSame([], $result->getData());
    }
}
