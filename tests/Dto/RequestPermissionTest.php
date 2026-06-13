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

        self::assertSame('sess_1', $request->getSessionId());
        self::assertSame('call_1', $request->getToolCallId());
        self::assertSame(
            ['toolCallId' => 'call_1', 'title' => 'Edit file'],
            $request->getToolCall(),
        );

        $options = $request->getOptions();
        self::assertCount(2, $options);
        self::assertSame('allow-once', $options[0]->getOptionId());
        self::assertSame('Allow once', $options[0]->getName());
        self::assertSame('allow_once', $options[0]->getKind());
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
        $this->expectExceptionMessage('Invalid session/request_permission params: toolCall.toolCallId must be a string');

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
