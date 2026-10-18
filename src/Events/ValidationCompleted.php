<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Events;

use Xmlvalid\Model\Event;
use Xmlvalid\Model\Validation;

/** An API validation finished: done, failed or cancelled (validation.completed). */
final class ValidationCompleted
{
    public function __construct(
        public readonly Validation $validation,
        public readonly Event $event,
    ) {}
}
