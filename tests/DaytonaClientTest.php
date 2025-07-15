<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\CommandResponse;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\Exceptions\ConfigurationException;
use Illuminate\Support\Facades\Http;

it('can be instantiated with config', function () {
    $config = new Config(
        apiKey: 'test-key',
        apiUrl: 'https://api.daytona.io',
        organizationId: 'test-org'
    );
    $client = new DaytonaClient($config);

    expect($client)->toBeInstanceOf(DaytonaClient::class);
});

it('can be instantiated from Laravel config', function () {
    // Ensure config is set
    config(['daytona.api_key' => 'test-key']);
    config(['daytona.api_url' => 'https://api.daytona.io']);
    config(['daytona.organization_id' => 'test-org']);

    $config = app(Config::class);
    $client = new DaytonaClient($config);

    expect($client)->toBeInstanceOf(DaytonaClient::class);
});

it('can be instantiated with typed configuration', function () {
    $config = new \ElliottLawson\Daytona\DTOs\Config(
        apiUrl: 'https://api.daytona.io',
        apiKey: 'test-key',
        organizationId: 'test-org'
    );

    $client = new DaytonaClient($config);

    expect($client)->toBeInstanceOf(DaytonaClient::class);
});

it('throws exception when api key is missing', function () {
    $config = new Config(
        apiKey: '',
        apiUrl: 'https://api.daytona.io',
        organizationId: 'test-org'
    );
    new DaytonaClient($config);
})->throws(ConfigurationException::class, 'Daytona API token is not configured');

it('can be resolved from container in Laravel', function () {
    $client = app(DaytonaClient::class);

    expect($client)->toBeInstanceOf(DaytonaClient::class);
});

it('passes command timeout to HTTP client with proper conversion', function () {
    Http::fake([
        '*/toolbox/*/toolbox/process/execute' => Http::response([
            'exitCode' => 0,
            'stdout' => 'Command output',
            'stderr' => '',
        ], 200),
    ]);

    $config = new Config(
        apiKey: 'test-key',
        apiUrl: 'https://api.daytona.io',
        organizationId: 'test-org'
    );
    $client = new DaytonaClient($config);

    // Test with 60 second timeout (60000ms)
    Http::preventStrayRequests();

    $response = $client->executeCommand('sandbox-123', 'long-running-command', null, null, 60000);

    expect($response)->toBeInstanceOf(CommandResponse::class);
    expect($response->exitCode)->toBe(0);
    expect($response->output)->toBe('Command output');

    // Verify the HTTP request was made
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'toolbox/sandbox-123/toolbox/process/execute') &&
               $request['timeout'] === 60000;
    });
});

it('uses default timeout when no command timeout is specified', function () {
    Http::fake([
        '*/toolbox/*/toolbox/process/execute' => Http::response([
            'exitCode' => 0,
            'stdout' => 'Command output',
            'stderr' => '',
        ], 200),
    ]);

    $config = new Config(
        apiKey: 'test-key',
        apiUrl: 'https://api.daytona.io',
        organizationId: 'test-org'
    );
    $client = new DaytonaClient($config);

    $response = $client->executeCommand('sandbox-123', 'quick-command');

    expect($response)->toBeInstanceOf(CommandResponse::class);
    expect($response->exitCode)->toBe(0);

    // Verify the request was made without timeout in payload
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'toolbox/sandbox-123/toolbox/process/execute') &&
               ! isset($request['timeout']);
    });
});
