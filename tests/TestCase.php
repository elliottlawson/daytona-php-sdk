<?php

namespace ElliottLawson\Daytona\Tests;

use ElliottLawson\Daytona\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('daytona.api_key', 'test-key');
    }
}
