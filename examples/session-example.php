<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\SessionExecuteRequest;
use ElliottLawson\Daytona\DaytonaClient;

// Configuration
$config = new Config(
    apiKey: getenv('DAYTONA_API_KEY') ?: 'your-api-key',
    apiUrl: getenv('DAYTONA_API_URL') ?: 'https://api.daytona.io',
);

$client = new DaytonaClient($config);

// You'll need a sandbox ID - replace with an actual sandbox ID
$sandboxId = 'your-sandbox-id';

try {
    echo "=== Session Example ===\n\n";

    // 1. Create a new session
    $sessionId = 'test-session-' . time();
    echo "Creating session: {$sessionId}\n";
    $session = $client->createSession($sandboxId, $sessionId);
    echo "Session created successfully\n";
    echo "Session ID: {$session->id}\n";
    echo "Created at: {$session->createdAt}\n\n";

    // 2. Execute a command to set an environment variable
    echo "Setting environment variable FOO=BAR\n";
    $request = new SessionExecuteRequest(
        command: 'export FOO=BAR',
        runAsync: false
    );
    $response = $client->executeSessionCommand($sandboxId, $sessionId, $request);
    echo "Command executed, cmdId: {$response->cmdId}\n\n";

    // 3. Execute another command to verify the environment variable persists
    echo "Checking environment variable\n";
    $request2 = new SessionExecuteRequest(
        command: 'echo $FOO',
        runAsync: false
    );
    $response2 = $client->executeSessionCommand($sandboxId, $sessionId, $request2);
    echo "Output: {$response2->output}\n";
    echo "Exit code: {$response2->exitCode}\n\n";

    // 4. Get session details
    echo "Getting session details\n";
    $sessionDetails = $client->getSession($sandboxId, $sessionId);
    echo "Session has " . count($sessionDetails->commands) . " commands\n";
    foreach ($sessionDetails->commands as $cmd) {
        echo "  - Command: {$cmd->command}, Exit Code: {$cmd->exitCode}\n";
    }
    echo "\n";

    // 5. Get command status
    if ($response2->cmdId) {
        echo "Getting command status for cmdId: {$response2->cmdId}\n";
        $commandStatus = $client->getSessionCommand($sandboxId, $sessionId, $response2->cmdId);
        echo "Command: {$commandStatus->command}\n";
        echo "Exit Code: {$commandStatus->exitCode}\n\n";

        // 6. Get command logs
        echo "Getting command logs\n";
        $logs = $client->getSessionCommandLogs($sandboxId, $sessionId, $response2->cmdId);
        echo "Logs: {$logs}\n\n";
    }

    // 7. Execute an async command
    echo "Executing async command\n";
    $asyncRequest = new SessionExecuteRequest(
        command: 'sleep 2 && echo "Async command completed"',
        runAsync: true
    );
    $asyncResponse = $client->executeSessionCommand($sandboxId, $sessionId, $asyncRequest);
    echo "Async command started, cmdId: {$asyncResponse->cmdId}\n\n";

    // 8. Stream logs (with callback)
    if ($asyncResponse->cmdId) {
        echo "Streaming logs for async command...\n";
        $client->getSessionCommandLogs(
            $sandboxId,
            $sessionId,
            $asyncResponse->cmdId,
            function ($chunk) {
                echo "Log chunk: {$chunk}";
            }
        );
        echo "\n\n";
    }

    // 9. List all sessions
    echo "Listing all sessions\n";
    $sessions = $client->listSessions($sandboxId);
    echo "Found " . count($sessions) . " sessions\n";
    foreach ($sessions as $s) {
        echo "  - Session ID: {$s->id}, Commands: " . count($s->commands) . "\n";
    }
    echo "\n";

    // 10. Delete the session
    echo "Deleting session: {$sessionId}\n";
    $client->deleteSession($sandboxId, $sessionId);
    echo "Session deleted successfully\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}