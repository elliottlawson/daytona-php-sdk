<?php

namespace ElliottLawson\Daytona\Tests\Integration;

use ElliottLawson\Daytona\Tests\TestCase;

class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load .env.testing if it exists
        if (file_exists(dirname(__DIR__, 2).'/.env.testing')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2), '.env.testing');
            $dotenv->load();
        }
    }
}