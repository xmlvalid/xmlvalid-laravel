<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Events;

use Xmlvalid\Model\Event;

/**
 * A monitored URL started failing (monitor.failed).
 *
 * $monitor: id, name, url, schema, interval_minutes, status;
 * $check: result, valid, errors_total, warnings_total, message, bytes, duration_ms, checked_at.
 */
final class MonitorFailed
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
