<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Http\Controllers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Xmlvalid\Exception\SignatureVerificationException;
use Xmlvalid\Laravel\Events\MonitorFailed;
use Xmlvalid\Laravel\Events\MonitorRecovered;
use Xmlvalid\Laravel\Events\ValidationCompleted;
use Xmlvalid\Laravel\Events\WebhookReceived;
use Xmlvalid\Model\Event;
use Xmlvalid\Webhook;

/** Verifies xmlvalid's webhooks and dispatches them as events. */
final class WebhookController
{
    public function __invoke(Request $request, Repository $config, Dispatcher $events): Response
    {
        $secret = $config->get('xmlvalid.webhook.secret');
        if (! is_string($secret) || $secret === '') {
            return new Response('Set XMLVALID_WEBHOOK_SECRET.', 403);
        }
        $tolerance = $config->get('xmlvalid.webhook.tolerance');

        try {
            $event = Webhook::verify(
                $request->getContent(),
                $request->headers->get('Xmlvalid-Signature') ?? '',
                $secret,
                is_numeric($tolerance) ? (int) $tolerance : 300,
            );
        } catch (SignatureVerificationException $e) {
            return new Response($e->getMessage(), 403);
        }

        $events->dispatch(new WebhookReceived($event));

        $validation = $event->validation();
        $typed = match ($event->type) {
            Event::VALIDATION_COMPLETED => $validation === null ? null : new ValidationCompleted($validation, $event),
            Event::MONITOR_FAILED => new MonitorFailed($this->object($event->data, 'monitor'), $this->object($event->data, 'check'), $event),
            Event::MONITOR_RECOVERED => new MonitorRecovered($this->object($event->data, 'monitor'), $this->object($event->data, 'check'), $event),
            default => null,
        };
        if ($typed !== null) {
            $events->dispatch($typed);
        }

        return new Response('', 204);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function object(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (! is_array($value)) {
            return [];
        }

        $object = [];
        foreach ($value as $name => $item) {
            $object[(string) $name] = $item;
        }

        return $object;
    }
}
