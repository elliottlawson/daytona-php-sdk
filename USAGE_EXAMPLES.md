# Daytona PHP SDK - File Operations Usage Examples

## Overview

The Daytona PHP SDK provides comprehensive file management capabilities for working with sandbox environments. Here are practical examples of all available file operations.

## Available File Operations

The SDK supports the following file operations:

### Basic File Operations
- **Read File** - Get file contents
- **Write File** - Create or update file contents  
- **Delete File** - Remove files
- **List Directory** - Get directory contents
- **File Exists** - Check if file exists

### Advanced File Operations
- **Create Directory** - Create directories with permissions
- **Move/Rename** - Move or rename files and directories
- **File Details** - Get comprehensive file metadata
- **Set Permissions** - Change file permissions and ownership
- **Search Files** - Find files by name patterns
- **Find in Files** - Search text content within files
- **Replace in Files** - Bulk text replacement across files

All operations support method chaining for complex workflows and include comprehensive error handling.

## Basic Setup

```php
use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\FilePermissionsParams;

$config = new Config(
    apiKey: 'your-api-key',
    apiUrl: 'https://your-daytona-instance.com',
    organizationId: 'your-org-id'
);

$client = new DaytonaClient($config);
$sandbox = $client->getSandboxById('sandbox-id');
```

## Directory Management

### Create Directories with Permissions

```php
// Create a directory with standard permissions
$sandbox->createFolder('/app/data', '755');

// Create a directory with custom permissions
$sandbox->createFolder('/app/uploads', '777');

// Chain directory creation
$sandbox
    ->createFolder('/app/logs', '755')
    ->createFolder('/app/cache', '755')
    ->createFolder('/app/temp', '777');
```

## File Information and Metadata

### Get Detailed File Information

```php
// Get comprehensive file details
$fileInfo = $sandbox->getFileDetails('/app/config.json');

echo "File: {$fileInfo->name}\n";
echo "Size: {$fileInfo->size} bytes\n";
echo "Owner: {$fileInfo->owner}\n";
echo "Group: {$fileInfo->group}\n";
echo "Mode: {$fileInfo->mode}\n";
echo "Permissions: {$fileInfo->permissions}\n";
echo "Modified: {$fileInfo->modifiedAt}\n";
echo "Is Directory: " . ($fileInfo->isDirectory ? 'Yes' : 'No') . "\n";
```

## File Operations

### Move/Rename Files and Directories

```php
// Rename a file
$sandbox->moveFile('/app/old-name.txt', '/app/new-name.txt');

// Move a file to different directory
$sandbox->moveFile('/app/temp/file.txt', '/app/data/file.txt');

// Move entire directories
$sandbox->moveFile('/app/old-folder', '/app/new-folder');

// Chain operations
$sandbox
    ->moveFile('/tmp/file1.txt', '/app/file1.txt')
    ->moveFile('/tmp/file2.txt', '/app/file2.txt');
```

## Permission Management

### Set File Permissions and Ownership

```php
// Set permissions using the DTO
$permissions = new FilePermissionsParams(
    mode: '644',
    owner: 'www-data',
    group: 'www-data'
);
$sandbox->setFilePermissions('/app/config.json', $permissions);

// Convenience method for quick permission changes
$sandbox->setPermissions('/app/script.sh', mode: '755');
$sandbox->setPermissions('/app/data/', owner: 'user', group: 'users');
$sandbox->setPermissions('/app/file.txt', mode: '644', owner: 'www-data');
```

## File Search Operations

### Search Files by Name Pattern

```php
// Find all TypeScript files
$result = $sandbox->searchFiles('/app/src', '*.ts');

echo "Found {$result->getCount()} TypeScript files:\n";
foreach ($result->files as $file) {
    echo "- $file\n";
}

// Search for configuration files
$configFiles = $sandbox->searchFiles('/app', 'config.*');

// Check if any files were found
if (!$configFiles->isEmpty()) {
    echo "Configuration files found!\n";
}
```

### Search Text Content Within Files

```php
// Find all TODO comments
$matches = $sandbox->findInFiles('/app/src', 'TODO:');

foreach ($matches as $match) {
    echo "File: {$match->file}\n";
    echo "Line {$match->line}: {$match->content}\n";
    echo "---\n";
}

// Search for specific function calls
$apiCalls = $sandbox->findInFiles('/app', 'api\\.call\\(');

// Search for error patterns
$errors = $sandbox->findInFiles('/app/logs', 'ERROR|FATAL');
```

## Text Replacement Operations

### Replace Text Across Multiple Files

```php
// Update version number across multiple files
$files = [
    '/app/package.json',
    '/app/composer.json',
    '/app/version.txt'
];

$results = $sandbox->replaceInFiles(
    files: $files,
    pattern: '"version": "1.0.0"',
    newValue: '"version": "1.1.0"'
);

// Check results
foreach ($results as $result) {
    if ($result->isSuccess()) {
        echo "✅ Updated: {$result->file}\n";
    } else {
        echo "❌ Failed: {$result->file} - {$result->error}\n";
    }
}
```

### Replace Text in Single File

```php
// Convenience method for single file replacement
$result = $sandbox->replaceInFile(
    file: '/app/config.php',
    pattern: 'debug = false',
    newValue: 'debug = true'
);

if ($result->isSuccess()) {
    echo "Debug mode enabled!\n";
} else {
    echo "Failed to update config: {$result->error}\n";
}
```

## Advanced Workflows

### Complete Project Setup

```php
// Create project structure with proper permissions
$sandbox
    ->createFolder('/app/src', '755')
    ->createFolder('/app/tests', '755')
    ->createFolder('/app/docs', '755')
    ->createFolder('/app/logs', '755')
    ->createFolder('/app/cache', '777')
    ->createFolder('/app/uploads', '777');

// Set proper permissions for sensitive files
$sandbox
    ->setPermissions('/app/config/database.php', '600', 'www-data', 'www-data')
    ->setPermissions('/app/config/app.php', '644', 'www-data', 'www-data');

// Make scripts executable
$sandbox->setPermissions('/app/scripts/deploy.sh', '755');
```

### Code Migration and Cleanup

```php
// Find all old API calls
$oldApiCalls = $sandbox->findInFiles('/app/src', 'oldApi\\(');

echo "Found " . count($oldApiCalls) . " old API calls to update:\n";
foreach ($oldApiCalls as $match) {
    echo "- {$match->file}:{$match->line}\n";
}

// Replace old API calls with new ones
$phpFiles = $sandbox->searchFiles('/app/src', '*.php');
$results = $sandbox->replaceInFiles(
    files: $phpFiles->files,
    pattern: 'oldApi\\(',
    newValue: 'newApi('
);

// Generate report
$successful = array_filter($results, fn($r) => $r->isSuccess());
$failed = array_filter($results, fn($r) => !$r->isSuccess());

echo "Migration complete!\n";
echo "✅ " . count($successful) . " files updated\n";
echo "❌ " . count($failed) . " files failed\n";
```

### File Organization

```php
// Find all temporary files
$tempFiles = $sandbox->searchFiles('/app', '*.tmp');

// Move them to temp directory
$sandbox->createFolder('/app/temp', '755');
foreach ($tempFiles->files as $file) {
    $basename = basename($file);
    $sandbox->moveFile($file, "/app/temp/$basename");
}

// Find and organize log files by date
$logFiles = $sandbox->searchFiles('/app', '*.log');
foreach ($logFiles->files as $logFile) {
    $details = $sandbox->getFileDetails($logFile);
    $date = date('Y-m-d', strtotime($details->modifiedAt));
    
    $sandbox
        ->createFolder("/app/logs/$date", '755')
        ->moveFile($logFile, "/app/logs/$date/" . basename($logFile));
}
```

## Error Handling

### Comprehensive Error Management

```php
use ElliottLawson\Daytona\Exceptions\FileSystemException;

try {
    // Perform file operations
    $sandbox
        ->createFolder('/restricted/area', '755')
        ->setPermissions('/app/secret.txt', '600', 'root', 'root');
        
} catch (FileSystemException $e) {
    // Handle specific file system errors
    match (true) {
        str_contains($e->getMessage(), 'permission denied') => 
            echo "Permission denied: Check sandbox access rights\n",
        str_contains($e->getMessage(), 'not found') => 
            echo "File not found: {$e->getMessage()}\n",
        default => 
            echo "File system error: {$e->getMessage()}\n"
    };
}
```