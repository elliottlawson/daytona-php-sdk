<?php

namespace ElliottLawson\Daytona;

use ElliottLawson\Daytona\DTOs\Config;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/daytona.php', 'daytona'
        );

        $this->app->singleton(Config::class, function (Application $app) {
            return new Config(
                apiKey: config('daytona.api_key'),
                apiUrl: config('daytona.api_url'),
                organizationId: config('daytona.organization_id'),
            );
        });

        $this->app->singleton(DaytonaClient::class, function (Application $app) {
            return new DaytonaClient($app->make(Config::class));
        });
        
        // Register the facade accessor
        $this->app->singleton('daytona', function (Application $app) {
            return $app->make(DaytonaClient::class);
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