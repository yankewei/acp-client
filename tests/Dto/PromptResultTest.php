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

        self::assertSame('end_turn', $result->getStopReason());
        self::assertTrue($result->isEndTurn());
        self::assertFalse($result->isCancelled());
        self::assertSame(['stopReason' => 'end_turn'], $result->getData());
    }

    public function testStopReasonHelpers(): void
    {
        self::assertTrue(PromptResult::fromArray(['stopReason' => 'max_tokens'])->isMaxTokens());
        self::assertTrue(PromptResult::fromArray(['stopReason' => 'max_turn_requests'])->isMaxTurnRequests());
        self::assertTrue(PromptResult::fromArray(['stopReason' => 'refusal'])->isRefusal());
        self::assertTrue(PromptResult::fromArray(['stopReason' => 'cancelled'])->isCancelled());
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
