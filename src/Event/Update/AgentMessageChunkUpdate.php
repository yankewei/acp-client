<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Dto\ContentBlock\ContentBlockFactory;
use Yankewei\AcpClient\Dto\ContentBlock\ContentBlockInterface;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class AgentMessageChunkUpdate implements SessionUpdate
{
    public function __construct(
        private readonly string $sessionId,
        private readonly ?string $messageId,
        private readonly ContentBlockInterface $content,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'agent_message_chunk') {
            throw new AcpException('Invalid agent_message_chunk update: sessionUpdate must be agent_message_chunk');
        }

        $content = Assert::requiredObjectField(
            $update,
            'content',
            'Invalid agent_message_chunk update: content must be an object',
        );

        return new self(
            $sessionId,
            Assert::optionalString(
                $update,
                'messageId',
                'Invalid agent_message_chunk update: messageId must be a string or null',
            ),
            ContentBlockFactory::fromArray($content),
        );
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'agent_message_chunk';
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getContentBlock(): ContentBlockInterface
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        return $this->content->toArray();
    }
}
