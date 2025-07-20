#!/usr/bin/env php
<?php

/**
 * Example: Preview URL with Long-Running Server
 *
 * This example demonstrates how to:
 * 1. Start a long-running web server in a session
 * 2. Get a preview URL to access the server
 * 3. Stream server logs in real-time
 * 4. Properly clean up resources
 */

require_once __DIR__.'/../vendor/autoload.php';

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\Exceptions\ApiException;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..');
$dotenv->load();

// Initialize the client
$client = new DaytonaClient($_ENV['DAYTONA_API_KEY'], $_ENV['DAYTONA_API_URL']);

try {
    echo "Creating sandbox...\n";
    $sandbox = $client->createSandbox();
    echo "Sandbox created: {$sandbox->getId()}\n\n";

    // Wait for sandbox to be ready
    echo "Starting sandbox...\n";
    $sandbox->start()->waitUntilStarted();
    echo "Sandbox started!\n\n";

    // Create a simple web application
    echo "Setting up web application...\n";

    // Create directory structure
    $sandbox->exec('mkdir -p /workspace/web-app');

    // Create index.php
    $indexContent = <<<'PHP'
<?php
// Simple web application
$routes = [
    '/' => function() {
        return ['message' => 'Welcome to the Daytona Preview URL Demo!', 'time' => date('Y-m-d H:i:s')];
    },
    '/api/status' => function() {
        return ['status' => 'healthy', 'uptime' => time() - $_SERVER['REQUEST_TIME']];
    },
    '/api/echo' => function() {
        $input = json_decode(file_get_contents('php://input'), true);
        return ['echo' => $input ?? 'No input provided'];
    }
];

// Simple router
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$handler = $routes[$path] ?? function() { return ['error' => 'Not found']; };

// JSON response
header('Content-Type: application/json');
echo json_encode($handler());
PHP;

    $sandbox->writeFile('/workspace/web-app/index.php', $indexContent);

    // Create a session for the server
    echo "Creating session for web server...\n";
    $session = $sandbox->createSession('web-server');

    // Start the PHP development server asynchronously
    echo "Starting PHP development server on port 8080...\n";
    $serverCommand = $session->executeCommand(
        'cd /workspace/web-app && php -S 0.0.0.0:8080',
        runAsync: true
    );

    // Wait a moment for the server to start
    sleep(2);

    // Get the preview URL
    echo "\nGetting preview URL...\n";
    $preview = $sandbox->getPreviewLink(8080);
    echo "Preview URL: {$preview->url}\n";
    echo "Access Token: {$preview->token}\n\n";

    echo "You can access your application at:\n";
    echo "- Homepage: {$preview->url}/\n";
    echo "- Status API: {$preview->url}/api/status\n";
    echo "- Echo API: {$preview->url}/api/echo\n\n";

    // Stream server logs
    echo "Streaming server logs (press Ctrl+C to stop)...\n";
    echo str_repeat('-', 60)."\n";

    // Set up signal handler for graceful shutdown
    $running = true;
    pcntl_signal(SIGINT, function () use (&$running) {
        $running = false;
        echo "\n\nShutting down...\n";
    });

    // Stream logs until interrupted
    while ($running) {
        pcntl_signal_dispatch();

        try {
            $serverCommand->streamLogs(function ($chunk) {
                echo $chunk;
                flush();
            });
        } catch (ApiException $e) {
            // Command may have stopped
            break;
        }

        // Small delay between streaming attempts
        usleep(100000); // 100ms
    }

} catch (ApiException $e) {
    echo 'Error: '.$e->getMessage()."\n";
    if ($e->getDetails()) {
        echo 'Details: '.json_encode($e->getDetails(), JSON_PRETTY_PRINT)."\n";
    }
} catch (Exception $e) {
    echo 'Unexpected error: '.$e->getMessage()."\n";
} finally {
    // Clean up
    echo "\nCleaning up...\n";

    if (isset($session)) {
        try {
            echo "Deleting session...\n";
            $session->delete();
        } catch (Exception $e) {
            echo 'Failed to delete session: '.$e->getMessage()."\n";
        }
    }

    if (isset($sandbox)) {
        try {
            echo "Deleting sandbox...\n";
            $sandbox->delete();
            echo "Cleanup complete!\n";
        } catch (Exception $e) {
            echo 'Failed to delete sandbox: '.$e->getMessage()."\n";
        }
    }
}
