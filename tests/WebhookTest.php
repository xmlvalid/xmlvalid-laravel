<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Tests;

use Illuminate\Support\Facades\Event;
use Xmlvalid\Laravel\Events\MonitorFailed;
use Xmlvalid\Laravel\Events\MonitorRecovered;
use Xmlvalid\Laravel\Events\ValidationCompleted;
use Xmlvalid\Laravel\Events\WebhookReceived;
use Xmlvalid\Laravel\Testing\XmlvalidFake;

final class WebhookTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $data
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function deliver(string $type, array $data, ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        $body = (string) json_encode(['id' => 'evt_1', 'type' => $type, 'created_at' => '2026-10-18T10:00:00+00:00', 'data' => $data]);
        $time = time();
        $signature = "t={$time},v1=" . hash_hmac('sha256', "{$time}.{$body}", $secret ?? self::SECRET);

        return $this->call('POST', '/xmlvalid/webhook', server: ['HTTP_XMLVALID_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], content: $body);
    }

    public function test_a_validation_event_is_dispatched(): void
    {
        Event::fake();

        $this->deliver('validation.completed', XmlvalidFake::validation(false, [['line' => 3, 'message' => 'Bad']]))->assertNoContent();

        Event::assertDispatched(WebhookReceived::class, fn(WebhookReceived $received): bool => $received->event->id === 'evt_1');
        Event::assertDispatched(ValidationCompleted::class, fn(ValidationCompleted $completed): bool => $completed->validation->id === 'val_fake'
            && (string) $completed->validation->errors()[0] === 'Line 3: Bad');
    }

    public function test_monitor_events_are_dispatched(): void
    {
        Event::fake();

        $this->deliver('monitor.failed', ['monitor' => ['id' => 7, 'name' => 'Feed'], 'check' => ['valid' => false, 'errors_total' => 4]])->assertNoContent();
        $this->deliver('monitor.recovered', ['monitor' => ['id' => 7], 'check' => ['valid' => true]])->assertNoContent();

        Event::assertDispatched(MonitorFailed::class, fn(MonitorFailed $failed): bool => $failed->monitor['name'] === 'Feed' && $failed->check['errors_total'] === 4);
        Event::assertDispatched(MonitorRecovered::class, fn(MonitorRecovered $recovered): bool => $recovered->check['valid'] === true);
    }

    public function test_a_ping_is_only_received(): void
    {
        Event::fake();

        $this->deliver('ping', ['message' => 'A test event from xmlvalid.com.'])->assertNoContent();

        Event::assertDispatchedTimes(WebhookReceived::class, 1);
        Event::assertNotDispatched(ValidationCompleted::class);
    }

    public function test_a_wrong_signature_is_refused(): void
    {
        Event::fake([WebhookReceived::class, ValidationCompleted::class, MonitorFailed::class, MonitorRecovered::class]);

        $this->deliver('ping', [], 'whsec_other')->assertForbidden();
        $this->call('POST', '/xmlvalid/webhook', content: '{}')->assertForbidden();

        Event::assertNothingDispatched();
    }

}
