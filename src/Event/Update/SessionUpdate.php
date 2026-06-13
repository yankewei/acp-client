<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

interface SessionUpdate
{
    public function getSessionId(): string;

    /**
     * Returns the value of params.update.sessionUpdate.
     */
    public function getUpdateType(): string;
}
