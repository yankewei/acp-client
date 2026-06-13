<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Event\Notification;
use Yankewei\AcpClient\Event\SessionInfoUpdate;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class SessionUpdateMapper
{
    /**
     * @throws AcpException
     */
    public static function fromNotification(Notification $notification): ?SessionUpdate
    {
        if (!$notification->is('session/update')) {
            return null;
        }

        $params = $notification->getParams();
        $sessionId = Assert::requiredString(
            $params,
            'sessionId',
            'Invalid session/update notification: sessionId must be a string',
        );

        $update = $params['update'] ?? null;
        if (!is_array($update) || array_is_list($update)) {
            return null;
        }

        $type = $update['sessionUpdate'] ?? null;
        if (!is_string($type)) {
            return null;
        }

        return match ($type) {
            'session_info_update' => SessionInfoUpdate::fromUpdate($sessionId, $update),
            'agent_message_chunk' => AgentMessageChunkUpdate::fromUpdate($sessionId, $update),
            'tool_call' => ToolCallUpdate::fromUpdate($sessionId, $update),
            'tool_call_update' => ToolCallStatusUpdate::fromUpdate($sessionId, $update),
            'usage_update' => UsageUpdate::fromUpdate($sessionId, $update),
            'plan' => PlanUpdate::fromUpdate($sessionId, $update),
            default => null,
        };
    }
}
