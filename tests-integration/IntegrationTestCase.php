<?php

namespace Tests;

use ElliottLawson\Daytona\DaytonaServiceProvider;
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
            DaytonaServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void {}
}
