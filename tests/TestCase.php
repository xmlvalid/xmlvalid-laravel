<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Tests;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use Xmlvalid\Laravel\XmlvalidServiceProvider;

abstract class TestCase extends Orchestra
{
    protected const SECRET = 'whsec_test';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [XmlvalidServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('xmlvalid.key', 'xv_test');
        $app['config']->set('xmlvalid.webhook.secret', self::SECRET);
    }

    protected function fixture(string $name): string
    {
        return __DIR__ . "/fixtures/{$name}";
    }
}
