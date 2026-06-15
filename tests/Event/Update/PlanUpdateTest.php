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

        static::assertInstanceOf(SessionUpdate::class, $update);
        static::assertSame('sess_1', $update->getSessionId());
        static::assertSame('plan', $update->getUpdateType());

        $entries = $update->getEntries();
        static::assertCount(3, $entries);

        static::assertInstanceOf(PlanEntry::class, $entries[0]);
        static::assertSame('First step', $entries[0]->getContent());
        static::assertSame('high', $entries[0]->getPriority());
        static::assertSame('in_progress', $entries[0]->getStatus());

        static::assertInstanceOf(PlanEntry::class, $entries[1]);
        static::assertSame('Second step', $entries[1]->getContent());
        static::assertSame('low', $entries[1]->getPriority());
        static::assertNull($entries[1]->getStatus());

        static::assertInstanceOf(PlanEntry::class, $entries[2]);
        static::assertSame('Third step', $entries[2]->getContent());
        static::assertNull($entries[2]->getPriority());
        static::assertSame('completed', $entries[2]->getStatus());
    }

    public function testRejectsWrongSessionUpdate(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid plan update: sessionUpdate must be plan');

        PlanUpdate::fromUpdate('sess_1', ['sessionUpdate' => 'tool_call']);
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
