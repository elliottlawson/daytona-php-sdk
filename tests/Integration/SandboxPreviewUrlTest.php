<?php

namespace ElliottLawson\Daytona\Tests\Integration;

use ElliottLawson\Daytona\DTOs\PortPreviewUrl;

class SandboxPreviewUrlTest extends IntegrationTestCase
{
    /** @test */
    public function it_can_get_preview_url_for_sandbox_port()
    {
        // Skip if not in integration test mode
        if (! $this->shouldRunIntegrationTests()) {
            $this->markTestSkipped('Integration tests are not enabled.');
        }

        // Create a sandbox for testing
        $sandbox = $this->createTestSandbox();

        try {
            // Act - Get preview URL for port 3000
            $previewInfo = $sandbox->getPreviewLink(3000);

            // Assert
            $this->assertInstanceOf(PortPreviewUrl::class, $previewInfo);
            $this->assertNotEmpty($previewInfo->url);
            $this->assertNotEmpty($previewInfo->token);

            // Verify URL format matches expected pattern
            $this->assertMatchesRegularExpression(
                '/^https:\/\/3000-[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/',
                $previewInfo->url
            );

            // Token should be a non-empty string
            $this->assertIsString($previewInfo->token);
            $this->assertGreaterThan(10, strlen($previewInfo->token));

            // Log the preview URL for manual testing if needed
            echo "\nPreview URL: {$previewInfo->url}\n";
            echo "Access Token: {$previewInfo->token}\n";

            if ($previewInfo->legacyProxyUrl) {
                echo "Legacy Proxy URL: {$previewInfo->legacyProxyUrl}\n";
            }
        } finally {
            // Cleanup
            $this->cleanupSandbox($sandbox);
        }
    }

    /** @test */
    public function it_can_get_preview_urls_for_multiple_ports()
    {
        // Skip if not in integration test mode
        if (! $this->shouldRunIntegrationTests()) {
            $this->markTestSkipped('Integration tests are not enabled.');
        }

        // Create a sandbox for testing
        $sandbox = $this->createTestSandbox();

        try {
            // Test multiple common ports
            $ports = [3000, 8080, 8000, 5000];
            $previewUrls = [];

            foreach ($ports as $port) {
                $previewInfo = $sandbox->getPreviewLink($port);
                $previewUrls[$port] = $previewInfo;

                // Assert each preview URL is unique and properly formatted
                $this->assertNotEmpty($previewInfo->url);
                $this->assertStringContainsString((string) $port, $previewInfo->url);
                $this->assertStringContainsString($sandbox->getId(), $previewInfo->url);
            }

            // Ensure all URLs are unique
            $urls = array_map(fn ($info) => $info->url, $previewUrls);
            $this->assertCount(count($ports), array_unique($urls));

            // Tokens might be the same for all ports in the same sandbox
            $tokens = array_map(fn ($info) => $info->token, $previewUrls);
            $this->assertNotEmpty($tokens[0]);
        } finally {
            // Cleanup
            $this->cleanupSandbox($sandbox);
        }
    }

    /** @test */
    public function it_handles_preview_url_for_non_standard_ports()
    {
        // Skip if not in integration test mode
        if (! $this->shouldRunIntegrationTests()) {
            $this->markTestSkipped('Integration tests are not enabled.');
        }

        // Create a sandbox for testing
        $sandbox = $this->createTestSandbox();

        try {
            // Test edge case ports
            $ports = [3001, 9999, 4000];

            foreach ($ports as $port) {
                $previewInfo = $sandbox->getPreviewLink($port);

                // Assert
                $this->assertInstanceOf(PortPreviewUrl::class, $previewInfo);
                $this->assertStringContainsString((string) $port, $previewInfo->url);
                $this->assertNotEmpty($previewInfo->token);
            }
        } finally {
            // Cleanup
            $this->cleanupSandbox($sandbox);
        }
    }
}
