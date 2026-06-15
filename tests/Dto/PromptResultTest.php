<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\PromptResult;
use Yankewei\AcpClient\Exception\AcpException;

final class PromptResultTest extends TestCase
{
    public function testFromArrayParsesStopReason(): void
    {
        $result = PromptResult::fromArray(['stopReason' => 'end_turn']);

        static::assertSame('end_turn', $result->getStopReason());
        static::assertTrue($result->isEndTurn());
        static::assertFalse($result->isCancelled());
        static::assertSame(['stopReason' => 'end_turn'], $result->getData());
    }

    public function testStopReasonHelpers(): void
    {
        static::assertTrue(PromptResult::fromArray(['stopReason' => 'max_tokens'])->isMaxTokens());
        static::assertTrue(PromptResult::fromArray(['stopReason' => 'max_turn_requests'])->isMaxTurnRequests());
        static::assertTrue(PromptResult::fromArray(['stopReason' => 'refusal'])->isRefusal());
        static::assertTrue(PromptResult::fromArray(['stopReason' => 'cancelled'])->isCancelled());
    }

    public function testFromArrayRejectsMissingStopReason(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Missing or invalid required field: stopReason');

        PromptResult::fromArray([]);
    }

    public function testFromArrayRejectsUnsupportedStopReason(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/prompt response: stopReason is not supported');

        PromptResult::fromArray(['stopReason' => 'unknown']);
    }
}
