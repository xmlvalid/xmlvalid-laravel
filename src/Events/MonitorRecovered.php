<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Events;

use Xmlvalid\Model\Event;

/** A monitored URL passes again (monitor.recovered); the same fields as MonitorFailed. */
final class MonitorRecovered
{
    /**
     * @param  array<string, mixed>  $monitor
     * @param  array<string, mixed>  $check
     */
    public function __construct(
        public readonly array $monitor,
        public readonly array $check,
        public readonly Event $event,
    ) {}
}
