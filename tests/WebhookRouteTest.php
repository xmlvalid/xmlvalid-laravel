<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Tests;

use Illuminate\Support\Facades\Route;

final class WebhookRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('xmlvalid.webhook.secret', null);
    }

    public function test_there_is_no_route_without_a_secret(): void
    {
        self::assertFalse(Route::has('xmlvalid.webhook'));
        $this->call('POST', '/xmlvalid/webhook', content: '{}')->assertNotFound();
    }
}
