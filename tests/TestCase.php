<?php

namespace ElliottLawson\Daytona\Tests;

use ElliottLawson\Daytona\DaytonaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DaytonaServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('daytona.api_url', 'https://api.daytona.io');
        config()->set('daytona.api_key', 'test-key');
    }
}