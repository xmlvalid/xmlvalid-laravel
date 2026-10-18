<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Xmlvalid\Client;
use Xmlvalid\Laravel\Console\SchemasCommand;
use Xmlvalid\Laravel\Console\ValidateCommand;
use Xmlvalid\Laravel\Http\Controllers\WebhookController;
use Xmlvalid\Laravel\Http\LaravelHttpClient;

final class XmlvalidServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/xmlvalid.php', 'xmlvalid');

        $this->app->singleton(Client::class, function (Application $app): Client {
            $config = $app->make(Repository::class);
            $key = $config->get('xmlvalid.key');
            if (! is_string($key) || $key === '') {
                throw new InvalidArgumentException('Set XMLVALID_API_KEY: an API key from https://xmlvalid.com/dashboard/api.');
            }

            $timeout = (float) $this->number($config->get('xmlvalid.timeout'), 900);

            return new Client(
                apiKey: $key,
                baseUrl: $this->string($config->get('xmlvalid.base_url'), Client::DEFAULT_BASE_URL),
                httpClient: new LaravelHttpClient($app->make(Factory::class), $timeout),
                timeout: $timeout,
                wait: (int) $this->number($config->get('xmlvalid.wait'), 10),
                maxRetries: (int) $this->number($config->get('xmlvalid.retries'), 3),
            );
        });
        $this->app->alias(Client::class, 'xmlvalid');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'xmlvalid');

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/xmlvalid.php' => $this->app->configPath('xmlvalid.php')], 'xmlvalid-config');
            $this->publishes([__DIR__ . '/../lang' => $this->app->langPath('vendor/xmlvalid')], 'xmlvalid-lang');
            $this->commands([ValidateCommand::class, SchemasCommand::class]);
        }

        $this->registerWebhookRoute();
    }

    /** POST xmlvalid/webhook, once a secret is set (cached routes have it already). */
    private function registerWebhookRoute(): void
    {
        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        $config = $this->app->make(Repository::class);
        $path = $config->get('xmlvalid.webhook.path');
        $secret = $config->get('xmlvalid.webhook.secret');
        if (! is_string($path) || $path === '' || ! is_string($secret) || $secret === '') {
            return;
        }

        $middleware = $config->get('xmlvalid.webhook.middleware');
        Route::post($path, WebhookController::class)
            ->middleware(is_array($middleware) ? array_values(array_filter($middleware, is_string(...))) : [])
            ->name('xmlvalid.webhook');
    }

    private function string(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function number(mixed $value, int|float $default): int|float
    {
        return is_numeric($value) ? +$value : $default;
    }
}
