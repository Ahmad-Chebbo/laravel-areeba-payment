<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment;

use AhmadChebbo\AreebaPayment\Services\AreebaGateway;
use AhmadChebbo\AreebaPayment\Support\AreebaClient;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AreebaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/areeba.php', 'areeba');

        $this->app->singleton(AreebaGateway::class, fn ($app) => new AreebaGateway(
            client: AreebaClient::fromConfig($app['config']['areeba']),
            merchantName: (string) $app['config']['areeba.merchant_name'],
            defaultCurrency: (string) $app['config']['areeba.currency'],
            notificationUrl: fn () => Route::has('areeba.webhook') ? route('areeba.webhook') : null,
        ));

        $this->app->alias(AreebaGateway::class, 'areeba');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'areeba');

        if (config('areeba.webhook.enabled')) {
            Route::middleware(config('areeba.webhook.middleware', []))
                ->group(fn () => $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php'));
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/areeba.php' => config_path('areeba.php'),
            ], 'areeba-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/areeba'),
            ], 'areeba-views');
        }
    }
}
