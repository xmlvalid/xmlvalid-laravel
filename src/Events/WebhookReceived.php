<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Events;

use Xmlvalid\Model\Event;

/**
 * Any verified webhook, ping included. The typed events (ValidationCompleted,
 * MonitorFailed, MonitorRecovered) follow it. A retry has the same
 * $event->id, so it can be used to skip events already handled.
 */
final class WebhookReceived
{
    public function __construct(public readonly Event $event) {}
}
