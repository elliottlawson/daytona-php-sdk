<?php

namespace ElliottLawson\Daytona;

use Illuminate\Support\ServiceProvider;

class DaytonaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/daytona.php', 'daytona'
        );

        $this->app->singleton(DaytonaClient::class, function ($app) {
            return new DaytonaClient($app['config']['daytona']);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/daytona.php' => config_path('daytona.php'),
            ], 'daytona-config');
        }
    }
}