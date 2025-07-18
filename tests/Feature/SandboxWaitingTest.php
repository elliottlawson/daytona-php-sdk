<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\SandboxResponse;
use ElliottLawson\Daytona\Exceptions\SandboxException;
use ElliottLawson\Daytona\Sandbox;
use Illuminate\Support\Facades\Http;

describe('Sandbox Waiting Mechanisms', function () {
    beforeEach(function () {
        $this->config = new Config(
            apiKey: 'test-api-key',
            apiUrl: 'https://api.example.com',
            organizationId: 'test-org'
        );
        $this->client = new DaytonaClient($this->config);
    });

    describe('Start Sandbox with Waiting', function () {
        it('waits until sandbox is started with default timeout', function () {
            // Mock sequence: start request -> starting state -> started state
            Http::fake([
                '*/sandbox/test-sandbox/start' => Http::response(['id' => 'test-sandbox', 'state' => 'starting'], 200),
                '*/sandbox/test-sandbox' => Http::sequence()
                    ->push(['id' => 'test-sandbox', 'state' => 'starting'], 200)
                    ->push(['id' => 'test-sandbox', 'state' => 'started'], 200),
            ]);

            $this->client->startSandbox('test-sandbox');

            // Verify start request was made
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'sandbox/test-sandbox/start');
            });

            // Verify status was checked
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'sandbox/test-sandbox') &&
                       $request->method() === 'GET';
            });
        });

        it('waits until sandbox is started with custom timeout', function () {
            Http::fake([
                '*/sandbox/test-sandbox/start' => Http::response(['id' => 'test-sandbox', 'state' => 'starting'], 200),
                '*/sandbox/test-sandbox' => Http::response(['id' => 'test-sandbox', 'state' => 'started'], 200),
            ]);

            $this->client->startSandbox('test-sandbox', 120);

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'sandbox/test-sandbox/start');
            });
        });

        it('does not wait when timeout is 0', function () {
            Http::fake([
                '*/sandbox/test-sandbox/start' => Http::response(['id' => 'test-sandbox', 'state' => 'starting'], 200),
            ]);

            $this->client->startSandbox('test-sandbox', 0);

            // Should only make the start request, not check status
            Http::assertSentCount(1);
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'sandbox/test-sandbox/start');
            });
        });

        it('throws timeout exception when sandbox does not start in time', function () {
            Http::fake([
                '*/sandbox/test-sandbox/start' => Http::response(['id' => 'test-sandbox', 'state' => 'starting'], 200),
                '*/sandbox/test-sandbox' => Http::response(['id' => 'test-sandbox', 'state' => 'starting'], 200),
            ]);

            $this->client->startSandbox('test-sandbox', 1); // 1 second timeout
        })->throws(SandboxException::class, 'failed to reach target state');

        it('throws state error when sandbox enters error state', function () {
            Http::fake([
                '*/sandbox/test-sandbox/start' => Http::response(['id' => 'test-sandbox', 'state' => 'starting'], 200),
                '*/sandbox/test-sandbox' => Http::response([
                    'id' => 'test-sandbox',
                    'state' => 'error',
                    'errorReason' => 'Failed to allocate resources',
                ], 200),
            ]);

            $this->client->startSandbox('test-sandbox');
        })->throws(SandboxException::class, 'entered error state');
    });

    describe('Stop Sandbox with Waiting', function () {
        it('waits until sandbox is stopped', function () {
            Http::fake([
                '*/sandbox/test-sandbox/stop' => Http::response(['id' => 'test-sandbox', 'state' => 'stopping'], 200),
                '*/sandbox/test-sandbox' => Http::sequence()
                    ->push(['id' => 'test-sandbox', 'state' => 'stopping'], 200)
                    ->push(['id' => 'test-sandbox', 'state' => 'stopped'], 200),
            ]);

            $this->client->stopSandbox('test-sandbox');

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'sandbox/test-sandbox/stop');
            });
        });

        it('throws timeout exception when sandbox does not stop in time', function () {
            Http::fake([
                '*/sandbox/test-sandbox/stop' => Http::response(['id' => 'test-sandbox', 'state' => 'stopping'], 200),
                '*/sandbox/test-sandbox' => Http::response(['id' => 'test-sandbox', 'state' => 'stopping'], 200),
            ]);

            $this->client->stopSandbox('test-sandbox', 1); // 1 second timeout
        })->throws(SandboxException::class, 'failed to reach target state');
    });

    describe('Sandbox Class Waiting Methods', function () {
        it('start method waits and returns self for chaining', function () {
            $sandboxData = [
                'id' => 'test-sandbox',
                'state' => 'started',
                'organizationId' => 'test-org',
                'user' => 'test-user',
                'env' => [],
                'labels' => [],
                'public' => false,
                'target' => 'test-target',
                'cpu' => 2,
                'gpu' => 0,
                'memory' => 4,
                'disk' => 20,
            ];

            Http::fake([
                '*/sandbox/test-sandbox/start' => Http::response($sandboxData, 200),
                '*/sandbox/test-sandbox' => Http::response($sandboxData, 200),
            ]);

            $sandboxResponse = SandboxResponse::fromArray($sandboxData);
            $sandbox = new Sandbox('test-sandbox', $this->client, $sandboxResponse);

            $result = $sandbox->start();

            expect($result)->toBeInstanceOf(Sandbox::class)
                ->and($result)->toBe($sandbox); // Should return same instance
        });

        it('stop method waits and returns self for chaining', function () {
            $sandboxData = [
                'id' => 'test-sandbox',
                'state' => 'stopped',
                'organizationId' => 'test-org',
                'user' => 'test-user',
                'env' => [],
                'labels' => [],
                'public' => false,
                'target' => 'test-target',
                'cpu' => 2,
                'gpu' => 0,
                'memory' => 4,
                'disk' => 20,
            ];

            Http::fake([
                '*/sandbox/test-sandbox/stop' => Http::response($sandboxData, 200),
                '*/sandbox/test-sandbox' => Http::response($sandboxData, 200),
            ]);

            $sandboxResponse = SandboxResponse::fromArray($sandboxData);
            $sandbox = new Sandbox('test-sandbox', $this->client, $sandboxResponse);

            $result = $sandbox->stop();

            expect($result)->toBeInstanceOf(Sandbox::class)
                ->and($result)->toBe($sandbox);
        });

        it('waitUntilStarted method waits for started state', function () {
            $sandboxData = [
                'id' => 'test-sandbox',
                'state' => 'started',
                'organizationId' => 'test-org',
                'user' => 'test-user',
                'env' => [],
                'labels' => [],
                'public' => false,
                'target' => 'test-target',
                'cpu' => 2,
                'gpu' => 0,
                'memory' => 4,
                'disk' => 20,
            ];

            Http::fake([
                '*/sandbox/test-sandbox' => Http::sequence()
                    ->push(['id' => 'test-sandbox', 'state' => 'starting'], 200)
                    ->push($sandboxData, 200), // Final state
            ]);

            $sandboxResponse = SandboxResponse::fromArray($sandboxData);
            $sandbox = new Sandbox('test-sandbox', $this->client, $sandboxResponse);

            $result = $sandbox->waitUntilStarted();

            expect($result)->toBeInstanceOf(Sandbox::class);
        });

        it('waitUntilStopped method waits for stopped state', function () {
            $sandboxData = [
                'id' => 'test-sandbox',
                'state' => 'stopped',
                'organizationId' => 'test-org',
                'user' => 'test-user',
                'env' => [],
                'labels' => [],
                'public' => false,
                'target' => 'test-target',
                'cpu' => 2,
                'gpu' => 0,
                'memory' => 4,
                'disk' => 20,
            ];

            Http::fake([
                '*/sandbox/test-sandbox' => Http::sequence()
                    ->push(['id' => 'test-sandbox', 'state' => 'stopping'], 200)
                    ->push($sandboxData, 200), // Final state
            ]);

            $sandboxResponse = SandboxResponse::fromArray($sandboxData);
            $sandbox = new Sandbox('test-sandbox', $this->client, $sandboxResponse);

            $result = $sandbox->waitUntilStopped();

            expect($result)->toBeInstanceOf(Sandbox::class);
        });

        it('supports fluent interface for chaining operations', function () {
            $sandboxData = [
                'id' => 'test-sandbox',
                'state' => 'started',
                'organizationId' => 'test-org',
                'user' => 'test-user',
                'env' => [],
                'labels' => [],
                'public' => false,
                'target' => 'test-target',
                'cpu' => 2,
                'gpu' => 0,
                'memory' => 4,
                'disk' => 20,
            ];

            Http::fake([
                '*/sandbox/test-sandbox/start' => Http::response($sandboxData, 200),
                '*/sandbox/test-sandbox' => Http::response($sandboxData, 200),
                '*/toolbox/test-sandbox/toolbox/process/execute' => Http::response([
                    'exitCode' => 0,
                    'stdout' => 'Hello World',
                    'stderr' => '',
                ], 200),
            ]);

            $sandboxResponse = SandboxResponse::fromArray($sandboxData);
            $sandbox = new Sandbox('test-sandbox', $this->client, $sandboxResponse);

            // Test fluent interface: start -> exec
            $result = $sandbox->start()->exec('echo "Hello World"');

            expect($result->output)->toBe('Hello World')
                ->and($result->exitCode)->toBe(0);
        });
    });

    describe('Generic State Waiting', function () {
        it('waitUntilSandboxState works with custom states', function () {
            Http::fake([
                '*/sandbox/test-sandbox' => Http::sequence()
                    ->push(['id' => 'test-sandbox', 'state' => 'pending'], 200)
                    ->push(['id' => 'test-sandbox', 'state' => 'ready'], 200),
            ]);

            $this->client->waitUntilSandboxState('test-sandbox', ['ready'], ['failed'], 60);

            Http::assertSentCount(2);
        });

        it('throws error when sandbox enters error state', function () {
            Http::fake([
                '*/sandbox/test-sandbox' => Http::response([
                    'id' => 'test-sandbox',
                    'state' => 'failed',
                    'errorReason' => 'Insufficient resources',
                ], 200),
            ]);

            $this->client->waitUntilSandboxState('test-sandbox', ['ready'], ['failed'], 60);
        })->throws(SandboxException::class, 'entered error state');
    });
});
