<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use ElliottLawson\Daytona\Exceptions\ApiException;
use ElliottLawson\Daytona\Exceptions\ConfigurationException;
use ElliottLawson\Daytona\Exceptions\SandboxException;
use Illuminate\Support\Facades\Http;

it('throws ConfigurationException when API key is missing', function () {
    $config = new Config(
        apiKey: '',
        apiUrl: 'https://api.daytona.io',
        organizationId: 'org-123'
    );

    new DaytonaClient($config);
})->throws(ConfigurationException::class, 'Daytona API token is not configured');

it('throws ApiException when creation fails with HTTP error', function () {
    Http::fake([
        '*/sandbox' => Http::response(['error' => 'Invalid parameters'], 400),
    ]);

    $client = app(DaytonaClient::class);
    $client->createSandbox(new SandboxCreateParameters);
})->throws(ApiException::class, 'API request failed: {"error":"Invalid parameters"}');

it('throws SandboxException when response is missing ID', function () {
    Http::fake([
        '*/sandbox' => Http::response(['status' => 'created'], 201),
    ]);

    $client = app(DaytonaClient::class);
    $client->createSandbox(new SandboxCreateParameters);
})->throws(SandboxException::class, 'Invalid response from Daytona API: missing sandbox ID');

it('throws ApiException when file read fails with HTTP error', function () {
    Http::fake([
        '*/toolbox/*/toolbox/files/download*' => Http::response(['error' => 'File not found'], 404),
    ]);

    $client = app(DaytonaClient::class);
    $client->readFile('sandbox-123', '/workspace/missing.txt');
})->throws(ApiException::class, 'Resource not found.');

it('throws ApiException when file write fails with HTTP error', function () {
    Http::fake([
        '*/toolbox/*/toolbox/files/upload*' => Http::response(['error' => 'Permission denied'], 403),
    ]);

    $client = app(DaytonaClient::class);
    $client->writeFile('sandbox-123', '/protected/file.txt', 'content');
})->throws(ApiException::class, 'Access denied. Please check your permissions.');

it('throws ApiException when directory listing fails with HTTP error', function () {
    Http::fake([
        '*/toolbox/*/toolbox/files*' => Http::response(['error' => 'Directory not found'], 404),
    ]);

    $client = app(DaytonaClient::class);
    $client->listDirectory('sandbox-123', '/missing/directory');
})->throws(ApiException::class, 'Resource not found.');

it('throws ApiException when command fails with HTTP error', function () {
    Http::fake([
        '*/toolbox/*/toolbox/process/execute' => Http::response(['error' => 'Command not found'], 400),
    ]);

    $client = app(DaytonaClient::class);
    $client->executeCommand('sandbox-123', 'invalid-command');
})->throws(ApiException::class, 'API request failed: {"error":"Command not found"}');

it('throws ApiException when clone fails with HTTP error', function () {
    Http::fake([
        '*/toolbox/*/toolbox/git/clone' => Http::response(['error' => 'Repository not found'], 404),
    ]);

    $client = app(DaytonaClient::class);
    $client->gitClone('sandbox-123', 'https://github.com/invalid/repo.git');
})->throws(ApiException::class, 'Resource not found.');

it('throws ApiException when commit fails with HTTP error', function () {
    Http::fake([
        '*/toolbox/*/toolbox/git/commit' => Http::response(['error' => 'Nothing to commit'], 400),
    ]);

    $client = app(DaytonaClient::class);
    $client->gitCommit('sandbox-123', '/workspace', 'Test commit', 'John Doe', 'john@example.com');
})->throws(ApiException::class, 'API request failed: {"error":"Nothing to commit"}');

it('throws ApiException with proper status code for authentication errors', function () {
    Http::fake([
        '*/sandbox/*' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $client = app(DaytonaClient::class);

    try {
        $client->getSandbox('sandbox-123');
    } catch (ApiException $e) {
        expect($e->getMessage())->toContain('Authentication failed');
        expect($e->getStatusCode())->toBe(401);
        throw $e;
    }
})->throws(ApiException::class);

it('throws ApiException for rate limiting', function () {
    Http::fake([
        '*/sandbox' => Http::response(['error' => 'Rate limit exceeded'], 429),
    ]);

    $client = app(DaytonaClient::class);

    try {
        $client->createSandbox(new SandboxCreateParameters);
    } catch (ApiException $e) {
        expect($e->getMessage())->toContain('Rate limit exceeded');
        expect($e->getStatusCode())->toBe(429);
        throw $e;
    }
})->throws(ApiException::class);

it('throws ApiException for server errors', function () {
    Http::fake([
        '*/sandbox' => Http::response(['error' => 'Internal server error'], 500),
    ]);

    $client = app(DaytonaClient::class);
    $client->createSandbox(new SandboxCreateParameters);
})->throws(ApiException::class, 'Server error. Please try again later.');

it('throws ApiException when start fails with HTTP error', function () {
    Http::fake([
        '*/sandbox/*/start' => Http::response(['error' => 'Cannot start sandbox'], 400),
    ]);

    $client = app(DaytonaClient::class);
    $client->startSandbox('sandbox-123');
})->throws(ApiException::class, 'API request failed: {"error":"Cannot start sandbox"}');

it('throws ApiException when stop fails with HTTP error', function () {
    Http::fake([
        '*/sandbox/*/stop' => Http::response(['error' => 'Cannot stop sandbox'], 400),
    ]);

    $client = app(DaytonaClient::class);
    $client->stopSandbox('sandbox-123');
})->throws(ApiException::class, 'API request failed: {"error":"Cannot stop sandbox"}');

it('throws ApiException when delete fails with HTTP error', function () {
    Http::fake([
        '*/sandbox/*' => Http::response(['error' => 'Cannot delete sandbox'], 400),
    ]);

    $client = app(DaytonaClient::class);
    $client->deleteSandbox('sandbox-123');
})->throws(ApiException::class);

it('throws specific exceptions for business logic errors', function () {
    // Test that SandboxException is thrown for missing ID (business logic error)
    Http::fake([
        '*/sandbox' => Http::response(['status' => 'created'], 201),
    ]);

    $client = app(DaytonaClient::class);
    $client->createSandbox(new SandboxCreateParameters);
})->throws(SandboxException::class, 'Invalid response from Daytona API: missing sandbox ID');
