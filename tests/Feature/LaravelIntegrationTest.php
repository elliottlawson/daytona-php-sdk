<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\Sandbox;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use Illuminate\Support\Facades\Http;

it('can resolve client from Laravel container', function () {
    // The TestCase already sets up config values
    $client = app(DaytonaClient::class);

    expect($client)->toBeInstanceOf(DaytonaClient::class);
});

it('can use client from config', function () {
    // The TestCase already sets up config values
    $config = app(Config::class);
    $client = new DaytonaClient($config);

    expect($client)->toBeInstanceOf(DaytonaClient::class);
});

it('uses ENV variables through config', function () {
    // Set specific config values
    config([
        'daytona.api_url' => 'https://custom.daytona.io',
        'daytona.api_key' => 'custom-test-key',
        'daytona.organization_id' => 'test-org-123'
    ]);

    // Mock the HTTP request to verify the correct values are used
    Http::fake(function ($request) {
        expect($request->url())->toContain('https://custom.daytona.io');
        expect($request->hasHeader('Authorization'))->toBeTrue();
        expect($request->header('Authorization')[0])->toBe('Bearer custom-test-key');
        expect($request->header('X-Daytona-Organization-ID')[0])->toBe('test-org-123');

        return Http::response(['id' => 'sandbox-123'], 200);
    });

    $config = app(Config::class);
    $client = new DaytonaClient($config);
    $client->getSandbox('sandbox-123');
});

it('can perform full workflow using Laravel integration', function () {
    // Mock sandbox creation
    Http::fake([
        '*/sandbox' => Http::response([
            'id' => 'sandbox-456',
            'name' => 'Laravel Test Sandbox',
            'status' => 'running',
            'state' => 'started',
            'organizationId' => 'org-123',
            'target' => 'us',
            'snapshot' => 'laravel-php84',
            'user' => 'daytona',
            'cpu' => 1,
            'memory' => 2,
            'disk' => 5,
            'createdAt' => now()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ], 201),
        '*/toolbox/*/toolbox/process/execute' => Http::response([
            'exitCode' => 0,
            'stdout' => 'Laravel 10.x',
            'stderr' => '',
        ], 200),
    ]);

    // Use dependency injection
    $client = app(DaytonaClient::class);

    // Create sandbox using proper parameters DTO
    $params = new SandboxCreateParameters(
        language: 'php',
        snapshot: 'laravel-php84',
        envVars: ['APP_ENV' => 'testing'],
    );

    $sandbox = $client->createSandbox($params);

    expect($sandbox)->toBeInstanceOf(Sandbox::class);
    expect($sandbox->getId())->toBe('sandbox-456');
    expect($sandbox->getState())->toBe('started');

    // Execute command
    $result = $sandbox->exec('php artisan --version');
    expect($result->isSuccessful())->toBeTrue();
    expect($result->output)->toContain('Laravel');
});