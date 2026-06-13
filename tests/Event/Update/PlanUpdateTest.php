<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event\Update;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Event\Update\PlanEntry;
use Yankewei\AcpClient\Event\Update\PlanUpdate;
use Yankewei\AcpClient\Event\Update\SessionUpdate;
use Yankewei\AcpClient\Exception\AcpException;

final class PlanUpdateTest extends TestCase
{
    public function testParsesMultipleEntries(): void
    {
        $update = PlanUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'plan',
            'entries' => [
                [
                    'content' => 'First step',
                    'priority' => 'high',
                    'status' => 'in_progress',
                ],
                [
                    'content' => 'Second step',
                    'priority' => 'low',
                ],
                [
                    'content' => 'Third step',
                    'status' => 'completed',
                ],
            ],
        ]);

        self::assertInstanceOf(SessionUpdate::class, $update);
        self::assertSame('sess_1', $update->getSessionId());
        self::assertSame('plan', $update->getUpdateType());

        $entries = $update->getEntries();
        self::assertCount(3, $entries);

        self::assertInstanceOf(PlanEntry::class, $entries[0]);
        self::assertSame('First step', $entries[0]->getContent());
        self::assertSame('high', $entries[0]->getPriority());
        self::assertSame('in_progress', $entries[0]->getStatus());

        self::assertInstanceOf(PlanEntry::class, $entries[1]);
        self::assertSame('Second step', $entries[1]->getContent());
        self::assertSame('low', $entries[1]->getPriority());
        self::assertNull($entries[1]->getStatus());

        self::assertInstanceOf(PlanEntry::class, $entries[2]);
        self::assertSame('Third step', $entries[2]->getContent());
        self::assertNull($entries[2]->getPriority());
        self::assertSame('completed', $entries[2]->getStatus());
    }

    public function testRejectsMissingEntries(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid plan update: entries must be a list');

        PlanUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'plan',
        ]);
    }

    public function testRejectsEntriesNotAList(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid plan update: entries must be a list');

        PlanUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'plan',
            'entries' => [
                'first' => ['content' => 'First step'],
            ],
        ]);
    }

    public function testRejectsEntryNotAnObject(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid plan update: entries[0] must be an object');

        PlanUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'plan',
            'entries' => ['First step'],
        ]);
    }

    public function testRejectsEntryMissingContent(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid plan entry: content must be a string');

        PlanUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'plan',
            'entries' => [
                ['priority' => 'high'],
            ],
        ]);
    }

    public function testRejectsInvalidPriorityType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid plan entry: priority must be a string or null');

        PlanUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'plan',
            'entries' => [
                [
                    'content' => 'First step',
                    'priority' => 123,
                ],
            ],
        ]);
    }

    public function testRejectsInvalidStatusType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid plan entry: status must be a string or null');

        PlanUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'plan',
            'entries' => [
                [
                    'content' => 'First step',
                    'status' => true,
                ],
            ],
        ]);
    }
}
