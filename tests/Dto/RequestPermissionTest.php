<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\RequestPermission;
use Yankewei\AcpClient\Exception\AcpException;

final class RequestPermissionTest extends TestCase
{
    public function testFromArrayParsesFields(): void
    {
        $request = RequestPermission::fromArray([
            'sessionId' => 'sess_1',
            'toolCall' => [
                'toolCallId' => 'call_1',
                'title' => 'Edit file',
            ],
            'options' => [
                [
                    'optionId' => 'allow-once',
                    'name' => 'Allow once',
                    'kind' => 'allow_once',
                ],
                [
                    'optionId' => 'reject-once',
                    'name' => 'Reject',
                    'kind' => 'reject_once',
                ],
            ],
        ]);

        static::assertSame('sess_1', $request->getSessionId());
        static::assertSame('call_1', $request->getToolCallId());
        static::assertSame(['toolCallId' => 'call_1', 'title' => 'Edit file'], $request->getToolCall());

        $options = $request->getOptions();
        static::assertCount(2, $options);
        static::assertSame('allow-once', $options[0]->getOptionId());
        static::assertSame('Allow once', $options[0]->getName());
        static::assertSame('allow_once', $options[0]->getKind());
    }

    public function testRejectsMissingSessionId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/request_permission params: sessionId must be a string');

        RequestPermission::fromArray([
            'toolCall' => ['toolCallId' => 'call_1'],
            'options' => [],
        ]);
    }

    public function testRejectsMissingToolCallId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Invalid session/request_permission params: toolCall.toolCallId must be a string',
        );

        RequestPermission::fromArray([
            'sessionId' => 'sess_1',
            'toolCall' => [],
            'options' => [],
        ]);
    }

    public function testRejectsInvalidOptionKind(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage(
            'Invalid permission option: kind must be one of allow_once, allow_always, reject_once, reject_always',
        );

        RequestPermission::fromArray([
            'sessionId' => 'sess_1',
            'toolCall' => ['toolCallId' => 'call_1'],
            'options' => [
                [
                    'optionId' => 'allow',
                    'name' => 'Allow',
                    'kind' => 'allow',
                ],
            ],
        ]);
    }
}
