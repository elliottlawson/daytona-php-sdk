<?php

namespace Tests\Integration;

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use ElliottLawson\Daytona\Sandbox;

/**
 * Shared helper trait for Daytona sandbox integration tests
 */
trait SandboxTestHelper
{
    protected DaytonaClient $client;

    /**
     * Set up the Daytona client before each test
     */
    protected function setupClient(): void
    {
        $apiKey = env('DAYTONA_API_KEY');

        if (!$apiKey) {
            $this->markTestSkipped('DAYTONA_API_KEY environment variable is not set');
        }

        $this->client = new DaytonaClient(new Config(
            apiKey: $apiKey,
            apiUrl: env('DAYTONA_API_URL', 'https://app.daytona.io/api'),
            organizationId: env('DAYTONA_ORGANIZATION_ID'),
        ));
    }

    /**
     * Clean up test sandboxes
     */
    protected function cleanupSandboxes(): void
    {
        try {
            $testSandboxes = $this->client->listSandboxes(['php-sdk-test' => 'true']);
            
            foreach ($testSandboxes as $sandbox) {
                try {
                    $sandbox->delete();
                } catch (\Exception $e) {
                    // Continue
                }
            }
        } catch (\Exception $e) {
            // Continue
        }
    }

}