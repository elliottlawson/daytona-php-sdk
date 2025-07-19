# Daytona PHP SDK

A PHP SDK for interacting with the Daytona API to manage development sandboxes.

## Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x (optional, for Laravel integration)

## Installation

```bash
composer require elliottlawson/daytona-php-sdk
```

## Configuration

### Laravel Applications

1. The service provider will be automatically registered via Laravel's package discovery.

2. Publish the configuration file:
```bash
php artisan vendor:publish --provider="ElliottLawson\Daytona\DaytonaServiceProvider" --tag="daytona-config"
```

3. Add your Daytona credentials to your `.env` file:
```env
DAYTONA_API_URL=https://api.daytona.io
DAYTONA_API_KEY=your-api-key
DAYTONA_ORGANIZATION_ID=your-org-id

# Optional configuration
DAYTONA_DEFAULT_SNAPSHOT=laravel-php84
DAYTONA_DEFAULT_MEMORY=2
DAYTONA_DEFAULT_DISK=2
DAYTONA_DEFAULT_CPU=1
DAYTONA_AUTO_STOP_INTERVAL=30
```

### Configuration Options

The SDK requires three configuration values:

- **`apiUrl`** (string): The Daytona API endpoint (defaults to `https://api.daytona.io`)
- **`apiKey`** (string, required): Your Daytona API authentication key
- **`organizationId`** (string, required): Your organization ID

### Type-Safe Configuration

For better type safety and IDE support, you can use the `Config` class:

```php
use ElliottLawson\Daytona\DTOs\Config;

$config = new Config(
    apiUrl: 'https://api.daytona.io',
    apiKey: 'your-api-key',
    organizationId: 'your-org-id'
);
```

### Non-Laravel Applications

You can instantiate the client directly with configuration:

```php
use ElliottLawson\Daytona\DaytonaClient;

$client = new DaytonaClient([
    'api_url' => 'https://api.daytona.io',
    'api_key' => 'your-api-key',
    'organization_id' => 'your-org-id' // optional
]);
```

## Usage

### Using the Facade (Laravel)

The easiest way to use Daytona in Laravel is through the facade:

```php
use Daytona;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;

// Create a sandbox
$sandbox = Daytona::createSandbox(new SandboxCreateParameters(
    language: 'php',
    snapshot: 'laravel-php84',
));

// Execute commands
$result = Daytona::executeCommand($sandbox->getId(), 'composer install');

// Work with files
Daytona::writeFile($sandbox->getId(), '/workspace/test.txt', 'Hello World');
$content = Daytona::readFile($sandbox->getId(), '/workspace/test.txt');

// Delete sandbox
Daytona::deleteSandbox($sandbox->getId());
```

### Creating a Client

#### In Laravel (Recommended)

```php
use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;

// Using dependency injection
public function __construct(DaytonaClient $client)
{
    $this->daytonaClient = $client;
}

// Using the service container
$client = app(DaytonaClient::class);

// Using without parameters (pulls from .env via config)
$client = new DaytonaClient();

// Using the config from container
$config = app(Config::class);
$client = new DaytonaClient($config);
```

#### Manual Configuration

```php
use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;

// Using typed configuration (recommended for type safety)
$config = new Config(
    apiUrl: 'https://api.daytona.io',
    apiKey: 'your-api-key',
    organizationId: 'your-org-id'
);
$client = new DaytonaClient($config);
```

### Managing Sandboxes

#### Create a Sandbox

```php
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;

// Create with minimal parameters
$sandbox = $client->createSandbox(new SandboxCreateParameters());

// Create with custom settings
$sandbox = $client->createSandbox(new SandboxCreateParameters(
    language: 'php',
    snapshot: 'laravel-php84',
    envVars: ['APP_ENV' => 'development'],
    memory: 4,
    disk: 10,
    cpu: 2,
    autoStopInterval: 30,
));

// The sandbox is automatically started and ready to use
echo $sandbox->getId(); // Sandbox ID
echo $sandbox->getState(); // 'started'
echo $sandbox->getRunnerDomain(); // Access URL

// Access sandbox data
$data = $sandbox->getData(); // Returns SandboxResponse DTO
$array = $sandbox->toArray(); // Returns array representation
```

#### Get Sandbox Information

```php
// Get a Sandbox object by ID
$sandbox = $client->getSandboxById($sandboxId);

// Access sandbox properties
echo $sandbox->getState(); // e.g., "started", "stopped"
echo $sandbox->getCreatedAt();
echo $sandbox->getSnapshot();
echo $sandbox->getCpu();
echo $sandbox->getMemory();

// Refresh data from API
$sandbox->refresh();

// Get raw SandboxResponse DTO if needed
$response = $client->getSandbox($sandboxId);
```

#### Start/Stop Sandbox

```php
// Using the Sandbox object (recommended)
$sandbox->start();
$sandbox->stop();
$sandbox->delete();

// Wait for state transitions
$sandbox->waitUntilStarted(timeout: 60); // Wait up to 60 seconds
$sandbox->waitUntilStopped(timeout: 30);

// Or using the client directly
$client->startSandbox($sandboxId);
$client->stopSandbox($sandboxId);
$client->deleteSandbox($sandboxId);
```

### Working with Files

#### Using the Sandbox Object (Recommended)

```php
// Read files
$content = $sandbox->readFile('/workspace/index.php');
echo $content;

// Write files
$sandbox->writeFile('/workspace/hello.txt', 'Hello, World!');

// List directory contents
$listing = $sandbox->listDirectory('/workspace');
foreach ($listing->files as $file) {
    echo $file->name . ' - ' . $file->type . PHP_EOL;
}

// Delete files
$sandbox->deleteFile('/workspace/temp.txt');

// Check file existence
if ($sandbox->fileExists('/workspace/config.php')) {
    echo "File exists!";
}
```

### Executing Commands

```php
// Using the Sandbox object (recommended)
$result = $sandbox->exec('ls -la');

// Check the result
if ($result->isSuccessful()) {
    echo $result->output;
} else {
    echo "Command failed with exit code: " . $result->exitCode;
    echo "Error: " . $result->error;
}

// Quick command execution
$result = $sandbox->exec('composer install');
echo $result->output;

// Note about exit codes:
// - Exit code 0 means success
// - Exit codes 1-255 indicate various error conditions
// - Exit code -1 means the actual exit code couldn't be determined by Daytona
if (!$result->hasKnownExitCode()) {
    echo "Warning: Exit code is unknown";
}

// Execute with specific working directory
$result = $sandbox->exec('ls -la', '/workspace');

// Execute with custom environment variables
$env = ['NODE_ENV' => 'production', 'API_KEY' => 'secret123'];
$result = $sandbox->exec('npm run build', null, $env);

// Execute with timeout (in milliseconds)
$timeout = 30000; // 30 seconds
$result = $sandbox->exec('npm test', null, null, $timeout);

// Execute with cwd, env and timeout
$result = $sandbox->exec('composer install', '/app', $env, $timeout);

// Or using the client directly
$result = $client->executeCommand($sandboxId, 'npm test', '/workspace', $env, $timeout);
```

### Git Operations

#### Using the Sandbox Object (Recommended)

```php
// Clone a repository
$sandbox->gitClone(
    'https://github.com/laravel/laravel.git',
    'main', // branch (optional)
    '/workspace', // destination path
    'username', // for private repos (optional)
    'password'  // for private repos (optional)
);

// Git status
$status = $sandbox->gitStatus('/workspace');
echo "Branch: " . $status->branch . PHP_EOL;
echo "Modified files: " . count($status->modified) . PHP_EOL;

// Git workflow with fluent interface
$sandbox->gitAdd('/workspace', ['file1.php', 'file2.php'])
    ->gitCommit('/workspace', 'Add new features', 'John Doe', 'john@example.com')
    ->gitPush('/workspace', 'username', 'password');
```

#### Using the Client Directly

```php
// List branches
$branches = $client->gitListBranches($sandboxId, '/workspace');
echo "Current branch: " . $branches->current . PHP_EOL;
foreach ($branches->branches as $branch) {
    echo "- " . $branch . PHP_EOL;
}

// Git history
$history = $client->gitHistory($sandboxId, '/workspace');
foreach ($history->commits as $commit) {
    echo $commit->hash . ' - ' . $commit->message . PHP_EOL;
}
```

### Complete Example

#### Using the Facade

```php
use Daytona;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;

// Create a sandbox
$sandbox = Daytona::createSandbox(new SandboxCreateParameters(
    language: 'php',
    snapshot: 'laravel-php84',
    envVars: ['APP_ENV' => 'testing'],
));

// Clone and setup a Laravel project
$sandbox->gitClone('https://github.com/laravel/laravel.git')
    ->exec('composer install')
    ->exec('cp .env.example .env')
    ->exec('php artisan key:generate');

// Run tests
$result = $sandbox->exec('php artisan test');
echo $result->output;

// Clean up
$sandbox->delete();
```

#### Using Dependency Injection

```php
use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;

class DeploymentService
{
    public function __construct(
        private DaytonaClient $daytona
    ) {}
    
    public function testDeployment(string $commitHash): bool
    {
        $sandbox = $this->daytona->createSandbox(new SandboxCreateParameters(
            language: 'php',
            snapshot: 'production-like',
        ));
        
        try {
            $sandbox->gitClone('https://github.com/mycompany/app.git')
                ->exec("git checkout {$commitHash}")
                ->exec('composer install --no-dev')
                ->exec('npm install && npm run build');
                
            $result = $sandbox->exec('./vendor/bin/phpunit');
            return $result->isSuccessful();
        } finally {
            $sandbox->delete();
        }
    }
}
```

### Advanced Usage

#### Working with Sandbox Data

```php
// Get all sandbox data
$data = $sandbox->getData(); // Returns SandboxResponse DTO
$array = $sandbox->toArray(); // Returns array

// Access specific properties
echo $sandbox->getState(); // 'started', 'stopped', etc.
echo $sandbox->getRunnerDomain(); // Sandbox URL
echo $sandbox->getCpu(); // CPU cores
echo $sandbox->getMemory(); // Memory in GB
echo $sandbox->getDisk(); // Disk in GB
echo $sandbox->getCreatedAt(); // Creation timestamp

// Check sandbox state
if ($sandbox->getState() === 'started') {
    // Sandbox is ready
}

// Refresh data from API
$sandbox->refresh();
```

#### State Management

```php
// Create sandbox without waiting
$sandbox = $client->createSandbox(
    new SandboxCreateParameters(snapshot: 'php-8.3'),
    waitForStart: false
);

// Manually wait for it to start
$sandbox->waitUntilStarted(timeout: 120); // Wait up to 2 minutes

// Stop and wait
$sandbox->stop()->waitUntilStopped();
```

#### Error Handling

The SDK provides specific exception types for different error scenarios:

```php
use ElliottLawson\Daytona\Exceptions\ConfigurationException;
use ElliottLawson\Daytona\Exceptions\SandboxException;
use ElliottLawson\Daytona\Exceptions\FileSystemException;
use ElliottLawson\Daytona\Exceptions\CommandExecutionException;
use ElliottLawson\Daytona\Exceptions\GitException;
use ElliottLawson\Daytona\Exceptions\ApiException;

try {
    $sandbox = $client->createSandbox(new SandboxCreateParameters());
    $result = $sandbox->exec('npm test');
    
    if (!$result->isSuccessful()) {
        // Handle command failure
        echo "Tests failed: " . $result->error;
    }
} catch (ConfigurationException $e) {
    // Handle configuration errors (missing API key, etc.)
    echo "Configuration error: " . $e->getMessage();
} catch (SandboxException $e) {
    // Handle sandbox-specific errors
    echo "Sandbox error: " . $e->getMessage();
} catch (CommandExecutionException $e) {
    // Handle command execution errors
    echo "Command failed: " . $e->getMessage();
} catch (FileSystemException $e) {
    // Handle file operation errors
    echo "File operation failed: " . $e->getMessage();
} catch (GitException $e) {
    // Handle Git operation errors
    echo "Git operation failed: " . $e->getMessage();
} catch (ApiException $e) {
    // Handle API errors with status codes
    echo "API error ({$e->getStatusCode()}): " . $e->getMessage();
    echo "Response: " . $e->getResponseBody();
}
```

**Exception Types:**
- `ConfigurationException` - Missing API keys, invalid configuration
- `SandboxException` - Sandbox creation, state transitions, lifecycle errors
- `FileSystemException` - File read/write/delete operations
- `CommandExecutionException` - Command execution failures
- `GitException` - Git operations (clone, commit, push, etc.)
- `ApiException` - HTTP API errors with status codes and responses

All exceptions extend the base `ElliottLawson\Daytona\Exception` class.
    echo $commit->hash . ' - ' . $commit->message . PHP_EOL;
    echo 'Author: ' . $commit->author . PHP_EOL;
    echo 'Date: ' . $commit->date . PHP_EOL;
}
```

### Using the Sandbox Object

For a more object-oriented approach, you can work with the `Sandbox` object:

```php
// Get a sandbox instance
$sandbox = new \ElliottLawson\Daytona\Sandbox($sandboxId, $client);

// Execute commands
$result = $sandbox->exec('npm install');

// File operations
$content = $sandbox->readFile('/workspace/package.json');
$sandbox->writeFile('/workspace/test.js', 'console.log("Hello");');

// Directory operations
$files = $sandbox->listDirectory('/workspace');

// Lifecycle management
$sandbox->start();
$sandbox->stop();
$sandbox->delete();
```

## Testing

```bash
# Run feature tests only (default)
composer test

# Run integration tests (requires Daytona server)
composer test:integration

# Run all tests
composer test:all

# Run tests with coverage
composer test-coverage
```

### Test Configuration

The test suite is split into two groups:
- **Feature tests**: Unit tests that don't require external services
- **Integration tests**: Tests that require a running Daytona server

By default, `composer test` or `vendor/bin/pest` only runs feature tests. This allows for quick testing during development without needing a Daytona server.

## Error Handling

All SDK methods throw `Exception` on errors:

```php
use ElliottLawson\Daytona\Exceptions\Exception;

try {
    $sandbox = $client->createSandbox([...]);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## License

MIT