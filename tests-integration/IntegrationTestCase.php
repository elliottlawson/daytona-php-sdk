<?php

namespace Tests;

use ElliottLawson\Daytona\DaytonaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class IntegrationTestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            DaytonaServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('daytona.api_key', env('DAYTONA_API_KEY', 'test-api-key'));
        config()->set('daytona.api_url', env('DAYTONA_API_URL', 'https://api.daytona.io'));
        config()->set('daytona.organization_id', env('DAYTONA_ORGANIZATION_ID', 'test-org'));
    }
}