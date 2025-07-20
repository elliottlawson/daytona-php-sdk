# Session Management

The Daytona PHP SDK provides comprehensive session management for long-running commands and processes. This is particularly useful for running development servers, build processes, or any command that needs to run continuously.

## Overview

Sessions allow you to:
- Execute commands asynchronously
- Stream logs in real-time
- Maintain state between commands
- Run multiple concurrent processes
- Track command execution status

## Basic Usage

### Creating a Session

```php
// Create a session with auto-generated ID
$session = $sandbox->createSession();

// Create a session with custom ID
$session = $sandbox->createSession('my-custom-session');
```

### Executing Commands

```php
// Execute command synchronously (blocks until complete)
$command = $session->executeCommand('echo "Hello, World!"');

// Execute command asynchronously (returns immediately)
$command = $session->executeCommand('npm run dev', runAsync: true);

// Execute with working directory and environment variables
$command = $session->executeCommand(
    'npm start',
    runAsync: true,
    cwd: '/workspace/my-app',
    env: ['NODE_ENV' => 'production', 'PORT' => '3000']
);
```

### Monitoring Command Execution

```php
// Get command status
$status = $command->getStatus();
if ($status->isRunning()) {
    echo "Command is still running\n";
} elseif ($status->isCompleted()) {
    echo "Command completed with exit code: {$status->exitCode}\n";
}

// Wait for command to complete (with timeout)
$finalStatus = $command->waitForCompletion(timeout: 300); // 5 minutes

// Get all logs after completion
$logs = $command->getLogs();
echo $logs;
```

### Real-time Log Streaming

```php
// Stream logs as they're generated
$command->streamLogs(function($chunk) {
    echo $chunk;
    flush(); // Ensure output is displayed immediately
});
```

### Session Management

```php
// List all sessions
$sessions = $sandbox->listSessions();
foreach ($sessions as $session) {
    echo "Session ID: {$session->id}\n";
}

// Get specific session
$session = $sandbox->getSession('session-id');

// Delete session (and all its commands)
$session->delete();
```

## Advanced Examples

### Running a Development Server with Preview URL

```php
// Create session for server
$session = $sandbox->createSession('dev-server');

// Start server asynchronously
$serverCmd = $session->executeCommand(
    'cd /workspace/app && npm run dev',
    runAsync: true
);

// Wait for server to start
sleep(3);

// Get preview URL
$preview = $sandbox->getPreviewLink(3000);
echo "Access your app at: {$preview->url}\n";

// Stream server logs
$serverCmd->streamLogs(function($chunk) {
    echo "[Server] " . $chunk;
});
```

### Parallel Build Process

```php
// Create session for builds
$session = $sandbox->createSession('build-session');

// Start multiple builds in parallel
$frontendBuild = $session->executeCommand('cd frontend && npm run build', runAsync: true);
$backendBuild = $session->executeCommand('cd backend && ./gradlew build', runAsync: true);

// Monitor both builds
while (true) {
    $frontendStatus = $frontendBuild->getStatus();
    $backendStatus = $backendBuild->getStatus();
    
    if ($frontendStatus->isFinished() && $backendStatus->isFinished()) {
        break;
    }
    
    echo "Frontend: {$frontendStatus->status}, Backend: {$backendStatus->status}\n";
    sleep(1);
}

// Check results
if ($frontendStatus->exitCode === 0 && $backendStatus->exitCode === 0) {
    echo "Both builds successful!\n";
}
```

### Using execAsync Convenience Method

```php
// Quick async execution without managing sessions
$command = $sandbox->execAsync('npm test');

// Wait and get results
$status = $command->waitForCompletion();
if ($status->exitCode === 0) {
    echo "Tests passed!\n";
    echo $command->getLogs();
}
```

## Best Practices

1. **Always clean up sessions**: Delete sessions when done to free resources
   ```php
   try {
       // Your code here
   } finally {
       $session->delete();
   }
   ```

2. **Use descriptive session IDs**: Make debugging easier
   ```php
   $session = $sandbox->createSession('build-' . date('Y-m-d-His'));
   ```

3. **Handle streaming interruptions**: Log streaming may be interrupted
   ```php
   try {
       $command->streamLogs($callback);
   } catch (ApiException $e) {
       // Handle interruption gracefully
   }
   ```

4. **Set appropriate timeouts**: Don't wait forever
   ```php
   $status = $command->waitForCompletion(timeout: 600); // 10 minutes max
   ```

5. **Check command status before streaming**: Avoid streaming completed commands
   ```php
   $status = $command->getStatus();
   if ($status->isRunning()) {
       $command->streamLogs($callback);
   }
   ```

## Error Handling

```php
try {
    $session = $sandbox->createSession();
    $command = $session->executeCommand('npm run build', runAsync: true);
    $status = $command->waitForCompletion();
    
    if ($status->exitCode !== 0) {
        $logs = $command->getLogs();
        throw new Exception("Build failed:\n" . $logs);
    }
} catch (ApiException $e) {
    // Handle API errors
    echo "API Error: " . $e->getMessage() . "\n";
} catch (CommandExecutionException $e) {
    // Handle command execution errors
    echo "Command Error: " . $e->getMessage() . "\n";
} finally {
    // Always clean up
    if (isset($session)) {
        $session->delete();
    }
}
```

## Limitations

- Sessions are tied to the sandbox lifecycle
- Log streaming requires an active connection
- Commands continue running even if your script disconnects
- Exit codes may be -1 if status is unknown