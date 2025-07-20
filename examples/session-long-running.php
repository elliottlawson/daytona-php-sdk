<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\SessionExecuteRequest;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use ElliottLawson\Daytona\DaytonaClient;

// Configuration
$config = new Config(
    apiKey: getenv('DAYTONA_API_KEY') ?: 'your-api-key',
    apiUrl: getenv('DAYTONA_API_URL') ?: 'https://api.daytona.io',
);

$client = new DaytonaClient($config);

try {
    echo "=== Long-Running Session Example ===\n\n";
    
    // Create or find a sandbox
    $sandboxId = getenv('DAYTONA_SANDBOX_ID');
    if (!$sandboxId) {
        echo "Creating new sandbox...\n";
        $sandbox = $client->createSandbox(new SandboxCreateParameters(
            labels: ['php-sdk-example' => 'true', 'type' => 'long-running']
        ));
        $sandboxId = $sandbox->getId();
        echo "Created sandbox: {$sandboxId}\n";
        
        // Wait for sandbox to be ready
        echo "Waiting for sandbox to start...\n";
        $sandbox->waitUntilStarted(60);
        echo "Sandbox is ready!\n\n";
    } else {
        echo "Using existing sandbox: {$sandboxId}\n\n";
        $sandbox = $client->getSandboxById($sandboxId);
    }
    
    // Create a session for our server
    $sessionId = 'php-server-' . time();
    echo "Creating session: {$sessionId}\n";
    $session = $client->createSession($sandboxId, $sessionId);
    echo "Session created successfully\n\n";
    
    // Create a simple PHP application
    echo "Setting up PHP application...\n";
    
    // Create directory structure
    $appDir = '/tmp/php-app-' . time();
    $mkdirRequest = new SessionExecuteRequest(
        command: "mkdir -p {$appDir}/public",
        runAsync: false
    );
    $client->executeSessionCommand($sandboxId, $sessionId, $mkdirRequest);
    
    // Create index.php
    $indexContent = <<<'PHP'
<?php
// Simple PHP application
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$time = date('Y-m-d H:i:s');

?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP SDK Demo Server</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .info { background: #f0f0f0; padding: 20px; border-radius: 5px; }
        .log { background: #333; color: #0f0; padding: 10px; font-family: monospace; }
    </style>
</head>
<body>
    <h1>🚀 PHP Development Server</h1>
    <div class="info">
        <p><strong>Request:</strong> <?= htmlspecialchars($method) ?> <?= htmlspecialchars($requestUri) ?></p>
        <p><strong>Time:</strong> <?= $time ?></p>
        <p><strong>PHP Version:</strong> <?= phpversion() ?></p>
    </div>
    <div class="log">
        <p>Server running on port 8080</p>
        <p>This server was started using Daytona PHP SDK!</p>
    </div>
</body>
</html>
PHP;
    
    $sandbox->writeFile("{$appDir}/public/index.php", $indexContent);
    echo "Created {$appDir}/public/index.php\n";
    
    // Create a simple API endpoint
    $apiContent = <<<'PHP'
<?php
header('Content-Type: application/json');

$response = [
    'status' => 'ok',
    'message' => 'Hello from Daytona PHP SDK!',
    'timestamp' => time(),
    'php_version' => phpversion()
];

echo json_encode($response, JSON_PRETTY_PRINT);
PHP;
    
    $sandbox->writeFile("{$appDir}/public/api.php", $apiContent);
    echo "Created {$appDir}/public/api.php\n\n";
    
    // Start PHP development server
    echo "Starting PHP development server on port 8080...\n";
    $serverRequest = new SessionExecuteRequest(
        command: "php -S 0.0.0.0:8080 -t {$appDir}/public",
        runAsync: true,
        cwd: $appDir
    );
    
    $serverResponse = $client->executeSessionCommand($sandboxId, $sessionId, $serverRequest);
    $serverCmdId = $serverResponse->cmdId;
    echo "Server command ID: {$serverCmdId}\n";
    
    // Wait for server to start
    echo "Waiting for server to start...\n";
    sleep(3);
    
    // Get preview URL
    try {
        echo "\nGetting preview URL...\n";
        $previewUrl = $sandbox->getPortPreviewUrl(8080);
        echo "✅ Preview URL: {$previewUrl->url}\n";
        if ($previewUrl->accessToken) {
            echo "   Access Token: " . substr($previewUrl->accessToken, 0, 20) . "...\n";
        }
        echo "\nYou can access:\n";
        echo "  - Main page: {$previewUrl->url}/\n";
        echo "  - API endpoint: {$previewUrl->url}/api.php\n";
    } catch (\Exception $e) {
        echo "⚠️  Could not get preview URL: " . $e->getMessage() . "\n";
        echo "   The server is still running, but external access may not be available.\n";
    }
    
    // Stream server logs
    echo "\n📋 Streaming server logs (press Ctrl+C to stop)...\n";
    echo str_repeat('-', 60) . "\n";
    
    $logBuffer = '';
    $lastLogTime = time();
    
    // Set up signal handler for graceful shutdown
    $running = true;
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGINT, function() use (&$running) {
            $running = false;
            echo "\n\nShutting down...\n";
        });
    }
    
    // Stream logs with proper handling
    try {
        while ($running) {
            // Check if we have pcntl functions for signal handling
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            
            // Stream logs with callback
            $client->getSessionCommandLogs(
                $sandboxId,
                $sessionId,
                $serverCmdId,
                function ($chunk) use (&$logBuffer, &$lastLogTime) {
                    // Print the chunk immediately
                    echo $chunk;
                    flush();
                    
                    // Update last log time
                    $lastLogTime = time();
                }
            );
            
            // Check server status
            $status = $client->getSessionCommand($sandboxId, $sessionId, $serverCmdId);
            if ($status->exitCode !== null && $status->exitCode !== -1) {
                echo "\nServer stopped with exit code: {$status->exitCode}\n";
                break;
            }
            
            // Small delay before next check
            usleep(500000); // 500ms
        }
    } catch (\Exception $e) {
        echo "\nError streaming logs: " . $e->getMessage() . "\n";
    }
    
    // Cleanup
    echo "\nCleaning up...\n";
    
    // Try to stop the server gracefully
    try {
        $stopRequest = new SessionExecuteRequest(
            command: "pkill -f 'php -S 0.0.0.0:8080'",
            runAsync: false
        );
        $client->executeSessionCommand($sandboxId, $sessionId, $stopRequest);
        echo "Server stopped\n";
    } catch (\Exception $e) {
        // Server might have already stopped
    }
    
    // Delete session
    try {
        $client->deleteSession($sandboxId, $sessionId);
        echo "Session deleted\n";
    } catch (\Exception $e) {
        echo "Could not delete session: " . $e->getMessage() . "\n";
    }
    
    // Optionally delete sandbox if we created it
    if (!getenv('DAYTONA_SANDBOX_ID')) {
        echo "\nDelete sandbox? (y/N): ";
        $answer = trim(fgets(STDIN));
        if (strtolower($answer) === 'y') {
            try {
                $sandbox->delete();
                echo "Sandbox deleted\n";
            } catch (\Exception $e) {
                echo "Could not delete sandbox: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\nDone!\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    
    // Cleanup on error
    if (isset($sessionId) && isset($sandboxId)) {
        try {
            $client->deleteSession($sandboxId, $sessionId);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }
}