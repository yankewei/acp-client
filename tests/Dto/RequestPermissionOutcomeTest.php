<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\RequestPermissionOutcome;
use Yankewei\AcpClient\Exception\AcpException;

final class RequestPermissionOutcomeTest extends TestCase
{
    public function testSelectedOutcome(): void
    {
        self::assertSame(
            [
                'outcome' => [
                    'outcome' => 'selected',
                    'optionId' => 'allow-once',
                ],
            ],
            RequestPermissionOutcome::selected('allow-once')->toResultArray(),
        );
    }

    public function testCancelledOutcome(): void
    {
        self::assertSame(
            [
                'outcome' => [
                    'outcome' => 'cancelled',
                ],
            ],
            RequestPermissionOutcome::cancelled()->toResultArray(),
        );
    }

    public function testRejectsEmptySelectedOptionId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid request permission outcome: optionId must be a non-empty string');

        RequestPermissionOutcome::selected('');
    }
}
