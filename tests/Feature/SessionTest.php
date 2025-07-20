<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\SessionCommandStatus;
use ElliottLawson\Daytona\DTOs\SessionExecuteRequest;
use ElliottLawson\Daytona\DTOs\SessionExecuteResponse;
use ElliottLawson\Daytona\DTOs\SessionResponse;
use ElliottLawson\Daytona\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->config = new Config(
        apiKey: 'test-key',
        apiUrl: 'https://api.test.com',
        organizationId: 'test-org'
    );
    $this->client = new DaytonaClient($this->config);
    $this->sandboxId = 'test-sandbox-id';
    $this->sessionId = 'test-session-'.time();
});

it('can create a session', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session' => Http::response([
            'id' => $this->sessionId,
            'createdAt' => now()->toIso8601String(),
            'commands' => [],
        ], 200),
    ]);

    $session = $this->client->createSession($this->sandboxId, $this->sessionId);

    expect($session)->toBeInstanceOf(SessionResponse::class);
    expect($session->id)->toBe($this->sessionId);
    expect($session->createdAt)->not->toBeNull();
    expect($session->commands)->toBeArray()->toBeEmpty();
});

it('can get session details', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session/*' => Http::response([
            'id' => $this->sessionId,
            'createdAt' => now()->toIso8601String(),
            'commands' => [
                [
                    'id' => 'cmd-1',
                    'command' => 'echo "Hello"',
                    'exitCode' => 0,
                ],
            ],
        ], 200),
    ]);

    $session = $this->client->getSession($this->sandboxId, $this->sessionId);

    expect($session)->toBeInstanceOf(SessionResponse::class);
    expect($session->id)->toBe($this->sessionId);
    expect($session->commands)->toHaveCount(1);
    expect($session->commands[0]->id)->toBe('cmd-1');
    expect($session->commands[0]->command)->toBe('echo "Hello"');
    expect($session->commands[0]->exitCode)->toBe(0);
});

it('can execute a synchronous command', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session/*/command' => Http::response([
            'cmdId' => 'cmd-123',
            'output' => 'Hello, World!',
            'exitCode' => 0,
        ], 200),
    ]);

    $request = new SessionExecuteRequest(
        command: 'echo "Hello, World!"',
        runAsync: false
    );

    $response = $this->client->executeSessionCommand($this->sandboxId, $this->sessionId, $request);

    expect($response)->toBeInstanceOf(SessionExecuteResponse::class);
    expect($response->cmdId)->toBe('cmd-123');
    expect($response->output)->toBe('Hello, World!');
    expect($response->exitCode)->toBe(0);
});

it('can execute an asynchronous command', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session/*/command' => Http::response([
            'cmdId' => 'cmd-456',
            'output' => null,
            'exitCode' => null,
        ], 200),
    ]);

    $request = new SessionExecuteRequest(
        command: 'sleep 5 && echo "Done"',
        runAsync: true
    );

    $response = $this->client->executeSessionCommand($this->sandboxId, $this->sessionId, $request);

    expect($response)->toBeInstanceOf(SessionExecuteResponse::class);
    expect($response->cmdId)->toBe('cmd-456');
    expect($response->output)->toBeNull();
    expect($response->exitCode)->toBeNull();
});

it('can execute command with working directory', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session/*/command' => Http::response([
            'cmdId' => 'cmd-789',
            'output' => '/home/user/test',
            'exitCode' => 0,
        ], 200),
    ]);

    $request = new SessionExecuteRequest(
        command: 'pwd',
        runAsync: false,
        cwd: '/home/user/test'
    );

    $response = $this->client->executeSessionCommand($this->sandboxId, $this->sessionId, $request);

    expect($response->output)->toBe('/home/user/test');
    expect($response->exitCode)->toBe(0);
});

it('can execute command with environment variables', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session/*/command' => Http::response([
            'cmdId' => 'cmd-env',
            'output' => 'BAR',
            'exitCode' => 0,
        ], 200),
    ]);

    $request = new SessionExecuteRequest(
        command: 'echo $FOO',
        runAsync: false,
        env: ['FOO' => 'BAR']
    );

    $response = $this->client->executeSessionCommand($this->sandboxId, $this->sessionId, $request);

    expect($response->output)->toBe('BAR');
    expect($response->exitCode)->toBe(0);
});

it('can get command status', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session/*/command/*' => Http::response([
            'id' => 'cmd-123',
            'command' => 'echo "Hello"',
            'exitCode' => 0,
        ], 200),
    ]);

    $status = $this->client->getSessionCommand($this->sandboxId, $this->sessionId, 'cmd-123');

    expect($status)->toBeInstanceOf(SessionCommandStatus::class);
    expect($status->id)->toBe('cmd-123');
    expect($status->command)->toBe('echo "Hello"');
    expect($status->exitCode)->toBe(0);
});

it('can get command logs without callback', function () {
    $logContent = "Line 1\nLine 2\nLine 3";

    Http::fake([
        '*/toolbox/*/toolbox/session/*/command/*/logs' => Http::response($logContent, 200),
    ]);

    $logs = $this->client->getSessionCommandLogs($this->sandboxId, $this->sessionId, 'cmd-123');

    expect($logs)->toBe($logContent);
});

it('can stream command logs with callback', function () {
    $chunks = [];
    $callback = function ($chunk) use (&$chunks) {
        $chunks[] = $chunk;
    };

    // Mock streaming response
    Http::fake([
        '*/toolbox/*/toolbox/session/*/command/*/logs' => Http::response(
            "Line 1\nLine 2\nLine 3\n",
            200,
            ['Transfer-Encoding' => 'chunked']
        ),
        '*/toolbox/*/toolbox/session/*/command/*' => Http::sequence()
            ->push(['id' => 'cmd-123', 'command' => 'test', 'exitCode' => null])
            ->push(['id' => 'cmd-123', 'command' => 'test', 'exitCode' => 0])
            ->push(['id' => 'cmd-123', 'command' => 'test', 'exitCode' => 0]),
    ]);

    // Note: In actual implementation, streaming would work differently
    // This is a simplified test for the callback functionality
    $this->client->getSessionCommandLogs($this->sandboxId, $this->sessionId, 'cmd-123', $callback);

    expect($chunks)->not->toBeEmpty();
});

it('can list all sessions', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session' => Http::response([
            [
                'id' => 'session-1',
                'createdAt' => now()->toIso8601String(),
                'commands' => [],
            ],
            [
                'id' => 'session-2',
                'createdAt' => now()->subHour()->toIso8601String(),
                'commands' => [
                    ['id' => 'cmd-1', 'command' => 'ls', 'exitCode' => 0],
                ],
            ],
        ], 200),
    ]);

    $sessions = $this->client->listSessions($this->sandboxId);

    expect($sessions)->toHaveCount(2);
    expect($sessions[0])->toBeInstanceOf(SessionResponse::class);
    expect($sessions[0]->id)->toBe('session-1');
    expect($sessions[0]->commands)->toBeEmpty();
    expect($sessions[1]->id)->toBe('session-2');
    expect($sessions[1]->commands)->toHaveCount(1);
});

it('can delete a session', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session/*' => Http::response([], 204),
    ]);

    $this->client->deleteSession($this->sandboxId, $this->sessionId);

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE' &&
               str_contains($request->url(), "session/{$this->sessionId}");
    });
});

it('handles session creation errors', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session' => Http::response(['error' => 'Session already exists'], 409),
    ]);

    $this->client->createSession($this->sandboxId, $this->sessionId);
})->throws(ApiException::class, 'Conflict - resource already exists or is in use.');

it('handles command execution errors', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session/*/command' => Http::response(['error' => 'Command failed'], 500),
    ]);

    $request = new SessionExecuteRequest(
        command: 'invalid-command',
        runAsync: false
    );

    $this->client->executeSessionCommand($this->sandboxId, $this->sessionId, $request);
})->throws(ApiException::class, 'Server error. Please try again later.');

it('preserves -1 exit code for unknown command status', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session/*/command/*' => Http::response([
            'id' => 'cmd-unknown',
            'command' => 'some-command',
            'exitCode' => -1,
        ], 200),
    ]);

    $status = $this->client->getSessionCommand($this->sandboxId, $this->sessionId, 'cmd-unknown');

    expect($status->exitCode)->toBe(-1);
});

it('correctly encodes command for synchronous execution', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session/*/command' => function ($request) {
            $body = $request->data();
            // Verify the command is properly wrapped
            expect($body['command'])->toContain('sh -c');
            expect($body['command'])->toContain('base64');
            expect($body['runAsync'])->toBe(false);

            return Http::response([
                'cmdId' => 'cmd-encoded',
                'output' => 'test output',
                'exitCode' => 0,
            ]);
        },
    ]);

    $request = new SessionExecuteRequest(
        command: 'echo "test"',
        runAsync: false
    );

    $response = $this->client->executeSessionCommand($this->sandboxId, $this->sessionId, $request);
    expect($response->cmdId)->toBe('cmd-encoded');
});

it('handles environment variables in command execution', function () {
    Http::fake([
        '*/toolbox/*/toolbox/session/*/command' => function ($request) {
            $body = $request->data();
            // Verify environment variables are properly encoded
            expect($body['command'])->toContain('export');
            expect($body['command'])->toContain('base64');

            return Http::response([
                'cmdId' => 'cmd-with-env',
                'output' => 'value1',
                'exitCode' => 0,
            ]);
        },
    ]);

    $request = new SessionExecuteRequest(
        command: 'echo $TEST_VAR',
        runAsync: false,
        env: ['TEST_VAR' => 'value1', 'ANOTHER_VAR' => 'value2']
    );

    $response = $this->client->executeSessionCommand($this->sandboxId, $this->sessionId, $request);
    expect($response->output)->toBe('value1');
});
