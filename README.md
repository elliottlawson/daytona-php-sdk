# Daytona PHP SDK

A PHP SDK for interacting with the Daytona API to manage development sandboxes.

## Installation

```bash
composer require elliottlawson/daytona-php-sdk
```

## Configuration

For Laravel applications, publish the configuration file:

```bash
php artisan vendor:publish --provider="ElliottLawson\Daytona\DaytonaServiceProvider"
```

## Basic Usage

```php
use ElliottLawson\Daytona\DaytonaClient;

$client = new DaytonaClient([
    'api_url' => 'https://api.daytona.io',
    'api_key' => 'your-api-key',
    'organization_id' => 'your-org-id'
]);

// Create a sandbox
$sandbox = $client->createSandbox([
    'snapshot' => 'laravel-php84',
    'memory' => 2,
    'disk' => 2,
    'cpu' => 1,
]);

// Execute a command
$result = $client->executeCommand($sandbox->id, 'ls -la');
```

## License

MIT