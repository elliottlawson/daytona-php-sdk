<?php

use ElliottLawson\Daytona\DTOs\PortPreviewUrl;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use Tests\Integration\SandboxTestHelper;

uses(SandboxTestHelper::class);

beforeEach(function () {
    $this->setupClient();
});

afterEach(function () {
    $this->cleanupSandboxes();
});

it('can get preview url for sandbox port', function () {
    // Create a sandbox for testing
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true', 'test-type' => 'preview-url']
    ));
    
    // Wait for sandbox to be ready
    $sandbox->waitUntilStarted(60);
    
    // Act - Get preview URL for port 3000
    $previewInfo = $sandbox->getPortPreviewUrl(3000);
    
    // Assert
    expect($previewInfo)->toBeInstanceOf(PortPreviewUrl::class);
    expect($previewInfo->url)->not->toBeEmpty();
    expect($previewInfo->accessToken)->not->toBeEmpty();
    
    // Verify URL format matches expected pattern
    expect($previewInfo->url)->toMatch('/^https:\/\/.*3000.*$/');
    
    // Token should be a non-empty string
    expect($previewInfo->accessToken)->toBeString();
    expect(strlen($previewInfo->accessToken))->toBeGreaterThan(10);
    
    // Log the preview URL for manual testing if needed
    echo "\nPreview URL: {$previewInfo->url}\n";
    echo "Access Token: {$previewInfo->accessToken}\n";
});

it('can get preview urls for multiple ports', function () {
    // Create a sandbox for testing
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true', 'test-type' => 'preview-url-multi']
    ));
    
    // Wait for sandbox to be ready
    $sandbox->waitUntilStarted(60);
    
    // Test multiple common ports
    $ports = [3000, 8080, 8000, 5000];
    $previewUrls = [];
    
    foreach ($ports as $port) {
        $previewInfo = $sandbox->getPortPreviewUrl($port);
        $previewUrls[$port] = $previewInfo;
        
        // Assert each preview URL is unique and properly formatted
        expect($previewInfo->url)->not->toBeEmpty();
        expect($previewInfo->url)->toContain((string) $port);
    }
    
    // Ensure all URLs are unique
    $urls = array_map(fn ($info) => $info->url, $previewUrls);
    expect(array_unique($urls))->toHaveCount(count($ports));
    
    // Tokens might be the same for all ports in the same sandbox
    $tokens = array_map(fn ($info) => $info->accessToken, $previewUrls);
    expect($tokens[0])->not->toBeEmpty();
});

it('handles preview url for non standard ports', function () {
    // Create a sandbox for testing
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true', 'test-type' => 'preview-url-edge']
    ));
    
    // Wait for sandbox to be ready
    $sandbox->waitUntilStarted(60);
    
    // Test edge case ports
    $ports = [3001, 9999, 4000];
    
    foreach ($ports as $port) {
        $previewInfo = $sandbox->getPortPreviewUrl($port);
        
        // Assert
        expect($previewInfo)->toBeInstanceOf(PortPreviewUrl::class);
        expect($previewInfo->url)->toContain((string) $port);
        expect($previewInfo->accessToken)->not->toBeEmpty();
    }
});