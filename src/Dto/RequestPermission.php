<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class RequestPermission
{
    /**
     * @param array<string, mixed> $toolCall
     * @param PermissionOption[] $options
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly array $toolCall,
        private readonly array $options,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $toolCall = Assert::requiredObjectField(
            $data,
            'toolCall',
            'Invalid session/request_permission params: toolCall must be an object',
        );

        Assert::requiredString(
            $toolCall,
            'toolCallId',
            'Invalid session/request_permission params: toolCall.toolCallId must be a string',
        );

        $options = Assert::list(
            $data['options'] ?? null,
            'Invalid session/request_permission params: options must be a list',
        );

        return new self(
            Assert::requiredString(
                $data,
                'sessionId',
                'Invalid session/request_permission params: sessionId must be a string',
            ),
            $toolCall,
            array_map(
                static function (mixed $option, int $index): PermissionOption {
                    return PermissionOption::fromArray(Assert::object(
                        $option,
                        "Invalid session/request_permission params: options[{$index}] must be an object",
                    ));
                },
                $options,
                array_keys($options),
            ),
        );
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getToolCall(): array
    {
        return $this->toolCall;
    }

    public function getToolCallId(): string
    {
        /** @var string $toolCallId */
        $toolCallId = $this->toolCall['toolCallId'];

        return $toolCallId;
    }

    /**
     * @return PermissionOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
