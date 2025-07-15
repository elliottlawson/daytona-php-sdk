<?php

namespace Tests;

use ElliottLawson\Daytona\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class IntegrationTestCase extends Orchestra
{
    protected function getBasePath(): string
    {
        return __DIR__.'/..';
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void {}
}
