<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class SessionInfoUpdateMapper
{
    private function __construct() {}

    /**
     * @throws AcpException
     */
    public static function fromNotification(Notification $notification): ?SessionInfoUpdate
    {
        if (!$notification->is('session/update')) {
            return null;
        }

        $params = $notification->getParams();
        $update = $params['update'] ?? null;
        if (!is_array($update) || array_is_list($update)) {
            return null;
        }

        if (($update['sessionUpdate'] ?? null) !== 'session_info_update') {
            return null;
        }

        $sessionId = Assert::requiredString(
            $params,
            'sessionId',
            'Invalid session_info_update notification: sessionId must be a string',
        );

        /** @var array<string, mixed> $update */
        return SessionInfoUpdate::fromUpdate($sessionId, $update);
    }
}
