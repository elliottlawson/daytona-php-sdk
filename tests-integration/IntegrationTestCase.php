<?php

namespace Tests;

use ElliottLawson\Daytona\DaytonaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class IntegrationTestCase extends Orchestra
{
    protected function getBasePath(): string
    {
        // Tell Orchestra where to find the package root
        return __DIR__.'/..';
    }

    protected function getPackageProviders($app): array
    {
        return [
            DaytonaServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Orchestra Testbench will automatically load .env.testing
        // when APP_ENV is set to 'testing'
    }
}
