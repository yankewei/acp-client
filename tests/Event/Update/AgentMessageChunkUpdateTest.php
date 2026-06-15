<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event\Update;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ContentBlock\ContentBlockInterface;
use Yankewei\AcpClient\Dto\ContentBlock\TextContentBlock;
use Yankewei\AcpClient\Event\Update\AgentMessageChunkUpdate;
use Yankewei\AcpClient\Event\Update\SessionUpdate;
use Yankewei\AcpClient\Exception\AcpException;

final class AgentMessageChunkUpdateTest extends TestCase
{
    public function testParsesTextChunk(): void
    {
        $update = AgentMessageChunkUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'agent_message_chunk',
            'messageId' => 'msg_1',
            'content' => ['type' => 'text', 'text' => 'Hello'],
        ]);

        static::assertInstanceOf(SessionUpdate::class, $update);
        static::assertSame('sess_1', $update->getSessionId());
        static::assertSame('agent_message_chunk', $update->getUpdateType());
        static::assertSame('msg_1', $update->getMessageId());
        static::assertInstanceOf(TextContentBlock::class, $update->getContentBlock());
        static::assertInstanceOf(ContentBlockInterface::class, $update->getContentBlock());
        static::assertSame(['type' => 'text', 'text' => 'Hello'], $update->getContent());
    }

    public function testMessageIdIsOptional(): void
    {
        $update = AgentMessageChunkUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'agent_message_chunk',
            'content' => ['type' => 'text', 'text' => 'Hello'],
        ]);

        static::assertNull($update->getMessageId());
    }

    public function testRejectsWrongDiscriminator(): void
    {
        $this->expectException(AcpException::class);

        AgentMessageChunkUpdate::fromUpdate('sess_1', ['sessionUpdate' => 'tool_call']);
    }

    public function testRejectsMissingContent(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid agent_message_chunk update: content must be an object');

        AgentMessageChunkUpdate::fromUpdate('sess_1', ['sessionUpdate' => 'agent_message_chunk']);
    }

    public function testRejectsListContent(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid agent_message_chunk update: content must be an object');

        AgentMessageChunkUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'agent_message_chunk',
            'content' => [['type' => 'text', 'text' => 'Hello']],
        ]);
    }

    public function testRejectsInvalidMessageIdType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid agent_message_chunk update: messageId must be a string or null');

        AgentMessageChunkUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'agent_message_chunk',
            'messageId' => 123,
            'content' => ['type' => 'text', 'text' => 'Hello'],
        ]);
    }
}
