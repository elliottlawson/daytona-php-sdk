<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->client = new DaytonaClient(new Config(
        apiKey: 'test-key',
        apiUrl: 'https://app.daytona.test/api',
    ));
});

$commandOutput = ['exitCode' => 0, 'result' => 'ok'];

it('sends toolbox requests to the proxy the sandbox advertises', function () use ($commandOutput) {
    Http::fake([
        'https://app.daytona.test/api/sandbox/sb-1' => Http::response([
            'id' => 'sb-1',
            'state' => 'started',
            'toolboxProxyUrl' => 'https://proxy.app-eu.daytona.test/toolbox',
        ]),
        'https://proxy.app-eu.daytona.test/toolbox/sb-1/process/execute' => Http::response($commandOutput),
    ]);

    $response = $this->client->executeCommand('sb-1', 'echo ok');

    expect($response->exitCode)->toBe(0);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://proxy.app-eu.daytona.test/toolbox/sb-1/process/execute');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/api/toolbox/'));
});

it('looks the proxy up once per sandbox', function () use ($commandOutput) {
    Http::fake([
        'https://app.daytona.test/api/sandbox/sb-1' => Http::response([
            'id' => 'sb-1',
            'toolboxProxyUrl' => 'https://proxy.daytona.test/toolbox',
        ]),
        'https://proxy.daytona.test/toolbox/sb-1/*' => Http::response($commandOutput),
    ]);

    $this->client->executeCommand('sb-1', 'echo one');
    $this->client->executeCommand('sb-1', 'echo two');

    Http::assertSentCount(3);
});

it('reuses the proxy from a created sandbox without another lookup', function () use ($commandOutput) {
    Http::fake([
        'https://app.daytona.test/api/sandbox' => Http::response([
            'id' => 'sb-new',
            'state' => 'started',
            'toolboxProxyUrl' => 'https://proxy.daytona.test/toolbox',
        ]),
        'https://proxy.daytona.test/toolbox/sb-new/process/execute' => Http::response($commandOutput),
    ]);

    $sandbox = $this->client->createSandbox(new SandboxCreateParameters);
    $this->client->executeCommand($sandbox->getId(), 'echo ok');

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request) => $request->method() === 'GET' && str_ends_with($request->url(), '/sandbox/sb-new'));
});

it('creates sessions and reads command logs through the proxy', function () {
    Http::fake([
        'https://app.daytona.test/api/sandbox/sb-1' => Http::response([
            'id' => 'sb-1',
            'toolboxProxyUrl' => 'https://proxy.daytona.test/toolbox',
        ]),
        'https://proxy.daytona.test/toolbox/sb-1/process/session' => Http::response([], 201),
        'https://proxy.daytona.test/toolbox/sb-1/process/session/s-1/command/c-1/logs' => Http::response('done'),
    ]);

    $this->client->createSession('sb-1', 's-1');
    $logs = $this->client->getSessionCommandLogs('sb-1', 's-1', 'c-1');

    expect($logs)->toBe('done');
});

it('falls back to the legacy toolbox route when no proxy is advertised', function () use ($commandOutput) {
    Http::fake([
        'https://app.daytona.test/api/sandbox/sb-1' => Http::response(['id' => 'sb-1']),
        'https://app.daytona.test/api/toolbox/sb-1/toolbox/process/execute' => Http::response($commandOutput),
    ]);

    $response = $this->client->executeCommand('sb-1', 'echo ok');

    expect($response->exitCode)->toBe(0);
});

it('exposes the proxy url on the sandbox response', function () {
    Http::fake([
        'https://app.daytona.test/api/sandbox/sb-1' => Http::response([
            'id' => 'sb-1',
            'toolboxProxyUrl' => 'https://proxy.daytona.test/toolbox',
        ]),
    ]);

    expect($this->client->getSandbox('sb-1')->toolboxProxyUrl)->toBe('https://proxy.daytona.test/toolbox');
});
