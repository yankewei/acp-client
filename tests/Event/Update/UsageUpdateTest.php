<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event\Update;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Event\Update\SessionUpdate;
use Yankewei\AcpClient\Event\Update\UsageUpdate;
use Yankewei\AcpClient\Exception\AcpException;

final class UsageUpdateTest extends TestCase
{
    public function testParsesUsageUpdateWithCost(): void
    {
        $update = UsageUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'usage_update',
            'used' => 10,
            'size' => 100,
            'cost' => [
                'amount' => 0.05,
                'currency' => 'USD',
            ],
        ]);

        static::assertInstanceOf(SessionUpdate::class, $update);
        static::assertSame('sess_1', $update->getSessionId());
        static::assertSame('usage_update', $update->getUpdateType());
        static::assertSame(10, $update->getUsed());
        static::assertSame(100, $update->getSize());
        static::assertSame(['amount' => 0.05, 'currency' => 'USD'], $update->getCost());
    }

    public function testParsesUsageUpdateWithoutCost(): void
    {
        $update = UsageUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'usage_update',
            'used' => 0,
            'size' => 0,
        ]);

        static::assertNull($update->getCost());
        static::assertSame(0, $update->getUsed());
        static::assertSame(0, $update->getSize());
    }

    public function testRejectsMissingUsed(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid usage_update update: used must be an integer');

        UsageUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'usage_update',
            'size' => 100,
        ]);
    }

    public function testRejectsMissingSize(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid usage_update update: size must be an integer');

        UsageUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'usage_update',
            'used' => 10,
        ]);
    }

    public function testRejectsNonIntegerUsed(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid usage_update update: used must be an integer');

        UsageUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'usage_update',
            'used' => '10',
            'size' => 100,
        ]);
    }

    public function testRejectsNonIntegerSize(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid usage_update update: size must be an integer');

        UsageUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'usage_update',
            'used' => 10,
            'size' => 100.5,
        ]);
    }

    public function testRejectsCostThatIsNotAnObject(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid usage_update update: cost must be an object');

        UsageUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'usage_update',
            'used' => 10,
            'size' => 100,
            'cost' => [['amount' => 0.05, 'currency' => 'USD']],
        ]);
    }

    public function testRejectsCostAmountThatIsNotANumber(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid usage_update update: cost.amount must be a number');

        UsageUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'usage_update',
            'used' => 10,
            'size' => 100,
            'cost' => [
                'amount' => ['value' => 0.05],
                'currency' => 'USD',
            ],
        ]);
    }

    public function testRejectsCostCurrencyThatIsNotAString(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid usage_update update: cost.currency must be a string');

        UsageUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'usage_update',
            'used' => 10,
            'size' => 100,
            'cost' => [
                'amount' => 0.05,
                'currency' => 123,
            ],
        ]);
    }

    public function testRejectsWrongDiscriminator(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid usage_update update: sessionUpdate must be usage_update');

        UsageUpdate::fromUpdate('sess_1', ['sessionUpdate' => 'tool_call']);
    }

    public function testRejectsMissingCostAmount(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid usage_update update: cost.amount must be a number');

        UsageUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'usage_update',
            'used' => 100,
            'size' => 200,
            'cost' => ['currency' => 'USD'],
        ]);
    }

    public function testRejectsMissingCostCurrency(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid usage_update update: cost.currency must be a string');

        UsageUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'usage_update',
            'used' => 100,
            'size' => 200,
            'cost' => ['amount' => 0.05],
        ]);
    }
}
