<?php

declare(strict_types=1);

namespace Yankewei\AcpClient;

use Yankewei\AcpClient\Event\Notification;

final class NotificationDispatcher
{
    /** @var array<int, callable(Notification): void> */
    private array $notificationListeners = [];

    /** @var array<string, array<int, callable(Notification): void>> */
    private array $methodListeners = [];

    /**
     * @param callable(Notification): void $listener
     */
    public function onNotification(callable $listener): void
    {
        $this->notificationListeners[] = $listener;
    }

    /**
     * @param callable(Notification): void $listener
     */
    public function offNotification(callable $listener): void
    {
        $this->notificationListeners = array_values(array_filter(
            $this->notificationListeners,
            static fn(callable $existing): bool => $existing !== $listener,
        ));
    }

    /**
     * @param callable(Notification): void $listener
     */
    public function on(string $method, callable $listener): void
    {
        $this->methodListeners[$method][] = $listener;
    }

    /**
     * @param callable(Notification): void $listener
     */
    public function off(string $method, callable $listener): void
    {
        if (!array_key_exists($method, $this->methodListeners)) {
            return;
        }

        $this->methodListeners[$method] = array_values(array_filter(
            $this->methodListeners[$method],
            static fn(callable $existing): bool => $existing !== $listener,
        ));
    }

    public function dispatch(Notification $notification): void
    {
        foreach ($this->notificationListeners as $listener) {
            $listener($notification);
        }

        foreach ($this->methodListeners[$notification->getMethod()] ?? [] as $listener) {
            $listener($notification);
        }
    }
}
