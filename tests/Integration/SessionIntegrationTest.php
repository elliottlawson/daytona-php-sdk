<?php

use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use ElliottLawson\Daytona\DTOs\SessionCommandStatus;
use ElliottLawson\Daytona\DTOs\SessionExecuteRequest;
use ElliottLawson\Daytona\DTOs\SessionExecuteResponse;
use ElliottLawson\Daytona\DTOs\SessionResponse;
use Tests\Integration\SandboxTestHelper;

/**
 * @group integration
 */
uses(SandboxTestHelper::class);

beforeEach(function () {
    $this->setupClient();

    // Create a sandbox for testing
    $this->sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true', 'test-type' => 'session-integration']
    ));

    // Wait for sandbox to be ready
    $this->sandbox->waitUntilStarted(60);
});

afterEach(function () {
    // Clean up test sessions
    try {
        $sessions = $this->client->listSessions($this->sandbox->getId());
        foreach ($sessions as $session) {
            try {
                $this->client->deleteSession($this->sandbox->getId(), $session->id);
            } catch (\Exception $e) {
                // Continue cleanup
            }
        }
    } catch (\Exception $e) {
        // Continue cleanup
    }

    $this->cleanupSandboxes();
});

it('can do things', function () {
    expect(true)->toBeTrue();
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters);

    $sandbox->createSession('test-session');
    $sandbox->listSessions();
    $sandbox->getSession('test-session');
});

it('can create and delete sessions', function () {
    $sessionId = 'test-session-'.uniqid();

    // Create session
    $session = $this->client->createSession($this->sandbox->getId(), $sessionId);
    expect($session)->toBeInstanceOf(SessionResponse::class);
    expect($session->id)->toBe($sessionId);

    // Get session details
    $sessionDetails = $this->client->getSession($this->sandbox->getId(), $sessionId);
    expect($sessionDetails->id)->toBe($sessionId);

    // List sessions should include our session
    $sessions = $this->client->listSessions($this->sandbox->getId());
    $sessionIds = array_map(fn ($s) => $s->id, $sessions);
    expect($sessionIds)->toContain($sessionId);

    // Delete session
    $this->client->deleteSession($this->sandbox->getId(), $sessionId);

    // Verify deletion
    $sessionsAfter = $this->client->listSessions($this->sandbox->getId());
    $sessionIdsAfter = array_map(fn ($s) => $s->id, $sessionsAfter);
    expect($sessionIdsAfter)->not->toContain($sessionId);
});

it('can execute synchronous commands and get output', function () {
    $sessionId = 'test-sync-'.uniqid();
    $session = $this->client->createSession($this->sandbox->getId(), $sessionId);

    // Execute a simple echo command
    $request = new SessionExecuteRequest(
        command: 'echo "Hello from PHP SDK"',
        runAsync: false
    );

    $response = $this->client->executeSessionCommand($this->sandbox->getId(), $sessionId, $request);
    expect($response)->toBeInstanceOf(SessionExecuteResponse::class);
    expect($response->cmdId)->not->toBeNull();
    expect($response->output)->toContain('Hello from PHP SDK');
    expect($response->exitCode)->toBe(0);

    // Get command logs
    $logs = $this->client->getSessionCommandLogs($this->sandbox->getId(), $sessionId, $response->cmdId);
    expect($logs)->toContain('Hello from PHP SDK');
});

it('can execute asynchronous commands and track status', function () {
    $sessionId = 'test-async-'.uniqid();
    $session = $this->client->createSession($this->sandbox->getId(), $sessionId);

    // Execute an async command
    $request = new SessionExecuteRequest(
        command: 'sleep 2 && echo "Async complete"',
        runAsync: true
    );

    $response = $this->client->executeSessionCommand($this->sandbox->getId(), $sessionId, $request);
    expect($response->cmdId)->not->toBeNull();
    expect($response->output)->toBeNull(); // Async commands don't return immediate output
    expect($response->exitCode)->toBeNull();

    // Check initial status
    $status = $this->client->getSessionCommand($this->sandbox->getId(), $sessionId, $response->cmdId);
    expect($status)->toBeInstanceOf(SessionCommandStatus::class);
    expect($status->id)->toBe($response->cmdId);

    // Wait for completion
    $maxWait = 10; // seconds
    $startTime = time();
    while (time() - $startTime < $maxWait) {
        $status = $this->client->getSessionCommand($this->sandbox->getId(), $sessionId, $response->cmdId);
        if ($status->exitCode !== null && $status->exitCode !== -1) {
            break;
        }
        sleep(1);
    }

    expect($status->exitCode)->toBe(0);

    // Get final logs
    $logs = $this->client->getSessionCommandLogs($this->sandbox->getId(), $sessionId, $response->cmdId);
    expect($logs)->toContain('Async complete');
});

it('maintains environment variables across commands in a session', function () {
    $sessionId = 'test-env-'.uniqid();
    $session = $this->client->createSession($this->sandbox->getId(), $sessionId);

    // Set environment variable
    $request1 = new SessionExecuteRequest(
        command: 'export TEST_VAR="Hello World"',
        runAsync: false
    );
    $response1 = $this->client->executeSessionCommand($this->sandbox->getId(), $sessionId, $request1);
    expect($response1->exitCode)->toBe(0);

    // Use the environment variable in another command
    $request2 = new SessionExecuteRequest(
        command: 'echo $TEST_VAR',
        runAsync: false
    );
    $response2 = $this->client->executeSessionCommand($this->sandbox->getId(), $sessionId, $request2);
    expect($response2->output)->toContain('Hello World');
    expect($response2->exitCode)->toBe(0);
});

it('can execute commands with custom working directory', function () {
    $sessionId = 'test-cwd-'.uniqid();
    $session = $this->client->createSession($this->sandbox->getId(), $sessionId);

    // Create a test directory
    $testDir = '/tmp/test-'.uniqid();
    $mkdirRequest = new SessionExecuteRequest(
        command: "mkdir -p {$testDir}",
        runAsync: false
    );
    $this->client->executeSessionCommand($this->sandbox->getId(), $sessionId, $mkdirRequest);

    // Execute command in custom directory
    $request = new SessionExecuteRequest(
        command: 'pwd',
        runAsync: false,
        cwd: $testDir
    );

    $response = $this->client->executeSessionCommand($this->sandbox->getId(), $sessionId, $request);
    expect($response->output)->toContain($testDir);
    expect($response->exitCode)->toBe(0);
});

it('can execute commands with environment variables', function () {
    $sessionId = 'test-env-cmd-'.uniqid();
    $session = $this->client->createSession($this->sandbox->getId(), $sessionId);

    // Execute command with environment variables
    $request = new SessionExecuteRequest(
        command: 'echo "$VAR1 $VAR2"',
        runAsync: false,
        env: [
            'VAR1' => 'Hello',
            'VAR2' => 'World',
        ]
    );

    $response = $this->client->executeSessionCommand($this->sandbox->getId(), $sessionId, $request);
    expect($response->output)->toContain('Hello World');
    expect($response->exitCode)->toBe(0);
});

it('can stream logs from long-running commands', function () {
    $sessionId = 'test-stream-'.uniqid();
    $session = $this->client->createSession($this->sandbox->getId(), $sessionId);

    // Start a command that outputs multiple lines over time
    $request = new SessionExecuteRequest(
        command: 'for i in 1 2 3 4 5; do echo "Line $i"; sleep 0.5; done',
        runAsync: true
    );

    $response = $this->client->executeSessionCommand($this->sandbox->getId(), $sessionId, $request);
    expect($response->cmdId)->not->toBeNull();

    // Stream logs
    $collectedLogs = '';
    $lineCount = 0;

    // Wait for command to complete
    sleep(3);

    $this->client->getSessionCommandLogs(
        $this->sandbox->getId(),
        $sessionId,
        $response->cmdId,
        function ($chunk) use (&$collectedLogs, &$lineCount) {
            $collectedLogs .= $chunk;
            $lineCount += substr_count($chunk, "\n");
        }
    );

    // The logs should contain all 5 lines
    expect($collectedLogs)->toContain('Line 1');
    expect($collectedLogs)->toContain('Line 5');
    // Count might be higher due to shell prompts or other output
    expect($lineCount)->toBeGreaterThanOrEqual(5);
});

it('can start a long-running process with preview URL support', function () {
    $sessionId = 'test-server-'.uniqid();
    $session = $this->client->createSession($this->sandbox->getId(), $sessionId);

    // Start a simple long-running process that outputs to demonstrate async execution
    $request = new SessionExecuteRequest(
        command: 'while true; do echo "Server running on port 8080 at $(date)"; sleep 5; done',
        runAsync: true
    );

    $response = $this->client->executeSessionCommand($this->sandbox->getId(), $sessionId, $request);
    expect($response->cmdId)->not->toBeNull();

    // Wait a moment for the process to start
    sleep(2);

    // Get preview URL for port 8080
    $previewUrl = $this->sandbox->getPreviewLink(8080);
    expect($previewUrl)->toBeInstanceOf(\ElliottLawson\Daytona\DTOs\PortPreviewUrl::class);
    expect($previewUrl->url)->toContain('https://');
    expect($previewUrl->url)->toContain('8080');

    // Verify the async command is still running
    $status = $this->client->getSessionCommand($this->sandbox->getId(), $sessionId, $response->cmdId);
    // For async commands, exitCode should be null while running
    expect($status->exitCode)->toBeNull();

    // Get some logs to verify it's outputting
    $logs = $this->client->getSessionCommandLogs($this->sandbox->getId(), $sessionId, $response->cmdId);
    expect($logs)->toContain('Server running on port 8080');
});

it('handles command failures correctly', function () {
    $sessionId = 'test-fail-'.uniqid();
    $session = $this->client->createSession($this->sandbox->getId(), $sessionId);

    // Execute a failing command (use false which exits with code 1)
    $request = new SessionExecuteRequest(
        command: 'false',
        runAsync: false
    );

    $response = $this->client->executeSessionCommand($this->sandbox->getId(), $sessionId, $request);
    expect($response->exitCode)->toBe(1);  // false command exits with 1

    // Check status only if we have a cmdId
    if ($response->cmdId !== null) {
        $status = $this->client->getSessionCommand($this->sandbox->getId(), $sessionId, $response->cmdId);
        expect($status->exitCode)->toBe(1);
    }
});

it('can run multiple sessions concurrently', function () {
    $session1Id = 'test-concurrent-1-'.uniqid();
    $session2Id = 'test-concurrent-2-'.uniqid();

    // Create two sessions
    $session1 = $this->client->createSession($this->sandbox->getId(), $session1Id);
    $session2 = $this->client->createSession($this->sandbox->getId(), $session2Id);

    // Set different variables in each session
    $request1 = new SessionExecuteRequest(
        command: 'export SESSION_NAME="Session 1"',
        runAsync: false
    );
    $this->client->executeSessionCommand($this->sandbox->getId(), $session1Id, $request1);

    $request2 = new SessionExecuteRequest(
        command: 'export SESSION_NAME="Session 2"',
        runAsync: false
    );
    $this->client->executeSessionCommand($this->sandbox->getId(), $session2Id, $request2);

    // Verify sessions are isolated
    $checkRequest = new SessionExecuteRequest(
        command: 'echo $SESSION_NAME',
        runAsync: false
    );

    $response1 = $this->client->executeSessionCommand($this->sandbox->getId(), $session1Id, $checkRequest);
    expect($response1->output)->toContain('Session 1');

    $response2 = $this->client->executeSessionCommand($this->sandbox->getId(), $session2Id, $checkRequest);
    expect($response2->output)->toContain('Session 2');
});
