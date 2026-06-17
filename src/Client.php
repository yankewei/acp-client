<?php

declare(strict_types=1);

namespace Yankewei\AcpClient;

use Yankewei\AcpClient\Transport\TransportInterface;

final class Client
{
    private NotificationDispatcher $notifications;

    private AgentRequestDispatcher $requests;

    private JsonRpcPeer $rpc;

    private Acp $acp;

    public function __construct(
        TransportInterface $transport,
        float $defaultTimeout = 30.0,
        bool $strictProtocol = true,
    ) {
        $this->notifications = new NotificationDispatcher();
        $this->requests = new AgentRequestDispatcher();
        $this->rpc = new JsonRpcPeer($transport, $this->notifications, $this->requests, $defaultTimeout);
        $this->acp = new Acp($this->rpc, $this->requests, new ProtocolValidator($strictProtocol));
    }

    public function acp(): Acp
    {
        return $this->acp;
    }

    public function rpc(): JsonRpcPeer
    {
        return $this->rpc;
    }

    public function notifications(): NotificationDispatcher
    {
        return $this->notifications;
    }

    public function requests(): AgentRequestDispatcher
    {
        return $this->requests;
    }
}
