<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\Sandbox;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $config = new Config(
        apiKey: 'test-api-key',
        apiUrl: 'https://api.example.com',
        organizationId: 'test-org'
    );
    $client = new DaytonaClient($config);
    $this->sandbox = new Sandbox('sandbox-123', $client);
});

describe('Project Setup Workflow', function () {
    it('can set up a complete project structure', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response([], 200),
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
            '*/toolbox/sandbox-123/toolbox/files/upload*' => Http::response(['success' => true], 200),
        ]);

        // Create project directory structure
        $this->sandbox
            ->createFolder('/app/src', '755')
            ->createFolder('/app/tests', '755')
            ->createFolder('/app/docs', '755')
            ->createFolder('/app/config', '755')
            ->createFolder('/app/logs', '755')
            ->createFolder('/app/cache', '777')
            ->createFolder('/app/uploads', '777');

        // Create configuration files
        $this->sandbox
            ->writeFile('/app/config/app.php', '<?php return ["debug" => false];')
            ->writeFile('/app/config/database.php', '<?php return ["driver" => "mysql"];');

        // Set proper permissions
        $this->sandbox
            ->setPermissions('/app/config/app.php', '644', 'www-data', 'www-data')
            ->setPermissions('/app/config/database.php', '600', 'www-data', 'www-data')
            ->setPermissions('/app/logs', '755', 'www-data', 'www-data')
            ->setPermissions('/app/cache', '777')
            ->setPermissions('/app/uploads', '777');

        // Verify all requests were made
        Http::assertSentCount(14); // 7 folders + 2 files + 5 permission sets
    });
});

describe('Code Migration Workflow', function () {
    it('can find and replace deprecated API calls across multiple files', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/find*' => Http::response([
                [
                    'file' => '/app/src/Controller.php',
                    'line' => 15,
                    'content' => '$result = oldApi($data);',
                ],
                [
                    'file' => '/app/src/Service.php',
                    'line' => 23,
                    'content' => 'return oldApi()->getData();',
                ],
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/search*' => Http::response([
                'files' => ['/app/src/Controller.php', '/app/src/Service.php', '/app/src/Helper.php'],
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response([
                [
                    'file' => '/app/src/Controller.php',
                    'success' => true,
                ],
                [
                    'file' => '/app/src/Service.php',
                    'success' => true,
                ],
                [
                    'file' => '/app/src/Helper.php',
                    'success' => true,
                ],
            ], 200),
        ]);

        // Find all old API calls
        $oldApiCalls = $this->sandbox->findInFiles('/app/src', 'oldApi\\(');
        expect($oldApiCalls)->toHaveCount(2);

        // Get all PHP files
        $phpFiles = $this->sandbox->searchFiles('/app/src', '*.php');
        expect($phpFiles->getCount())->toBe(3);

        // Replace old API calls
        $results = $this->sandbox->replaceInFiles(
            $phpFiles->files,
            'oldApi\\(',
            'newApi('
        );

        // Verify all replacements succeeded
        expect($results)->toHaveCount(3);
        foreach ($results as $result) {
            expect($result->isSuccess())->toBeTrue();
        }

        Http::assertSentCount(3); // find + search + replace
    });

    it('handles mixed success and failure in bulk operations', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/search*' => Http::response([
                'files' => ['/app/writable.php', '/app/readonly.php', '/app/missing.php'],
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response([
                [
                    'file' => '/app/writable.php',
                    'success' => true,
                ],
                [
                    'file' => '/app/readonly.php',
                    'success' => false,
                    'error' => 'Permission denied',
                ],
                [
                    'file' => '/app/missing.php',
                    'success' => false,
                    'error' => 'File not found',
                ],
            ], 200),
        ]);

        $phpFiles = $this->sandbox->searchFiles('/app', '*.php');
        $results = $this->sandbox->replaceInFiles(
            $phpFiles->files,
            'old_value',
            'new_value'
        );

        $successful = array_values(array_filter($results, fn ($r) => $r->isSuccess()));
        $failed = array_values(array_filter($results, fn ($r) => ! $r->isSuccess()));

        expect($successful)->toHaveCount(1);
        expect($failed)->toHaveCount(2);
        expect($failed[0]->error)->toBe('Permission denied');
        expect($failed[1]->error)->toBe('File not found');
    });
});

describe('File Organization Workflow', function () {
    it('can organize files by type and date', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/search?path=%2Fapp&pattern=*.log' => Http::response([
                'files' => ['/app/log1.log', '/app/log2.log'],
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/search?path=%2Fapp&pattern=*.tmp' => Http::response([
                'files' => ['/app/temp1.tmp', '/app/temp2.tmp'],
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/info*' => Http::response([
                'name' => 'log1.log',
                'isDir' => false,
                'size' => 1024,
                'modTime' => '2024-01-15T10:30:00Z',
                'mode' => '644',
                'permissions' => 'rw-r--r--',
                'owner' => 'www-data',
                'group' => 'www-data',
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response([], 200),
            '*/toolbox/sandbox-123/toolbox/files/move*' => Http::response([], 200),
        ]);

        // Find log files
        $logFiles = $this->sandbox->searchFiles('/app', '*.log');
        expect($logFiles->getCount())->toBe(2);

        // Find temp files
        $tempFiles = $this->sandbox->searchFiles('/app', '*.tmp');
        expect($tempFiles->getCount())->toBe(2);

        // Get file details for organization
        $fileDetails = $this->sandbox->getFileDetails('/app/log1.log');
        expect($fileDetails->modifiedAt)->toBe('2024-01-15T10:30:00Z');

        // Create organized directory structure
        $this->sandbox
            ->createFolder('/app/logs/2024-01-15', '755')
            ->createFolder('/app/temp', '755');

        // Move files to organized locations
        foreach ($logFiles->files as $logFile) {
            $basename = basename($logFile);
            $this->sandbox->moveFile($logFile, "/app/logs/2024-01-15/$basename");
        }

        foreach ($tempFiles->files as $tempFile) {
            $basename = basename($tempFile);
            $this->sandbox->moveFile($tempFile, "/app/temp/$basename");
        }

        // Verify HTTP calls - 2 searches + 1 info + 2 folders + 4 moves = 9 total
        Http::assertSentCount(9);
    });
});

describe('Security Hardening Workflow', function () {
    it('can audit and fix file permissions across project', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/search*' => Http::response([
                'files' => ['/app/config.php', '/app/secret.key', '/app/script.sh'],
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/info*' => Http::response([
                'name' => 'config.php',
                'isDir' => false,
                'size' => 1024,
                'modTime' => '2024-01-15T10:30:00Z',
                'mode' => '777', // Insecure!
                'permissions' => 'rwxrwxrwx',
                'owner' => 'root',
                'group' => 'root',
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
        ]);

        // Find sensitive files
        $configFiles = $this->sandbox->searchFiles('/app', 'config.*');
        $secretFiles = $this->sandbox->searchFiles('/app', '*.key');
        $scriptFiles = $this->sandbox->searchFiles('/app', '*.sh');

        // Audit a config file
        $configDetails = $this->sandbox->getFileDetails('/app/config.php');
        expect($configDetails->mode)->toBe('777'); // Detected insecure permissions!

        // Fix permissions
        $this->sandbox
            // Secure config files
            ->setPermissions('/app/config.php', '644', 'www-data', 'www-data')
            // Highly secure secret files
            ->setPermissions('/app/secret.key', '600', 'www-data', 'www-data')
            // Executable scripts
            ->setPermissions('/app/script.sh', '755', 'www-data', 'www-data');

        Http::assertSentCount(7); // 3 searches + 1 info + 3 permission sets
    });
});

describe('Development Environment Setup', function () {
    it('can set up a complete development environment', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response([], 200),
            '*/toolbox/sandbox-123/toolbox/files/upload*' => Http::response(['success' => true], 200),
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response([
                [
                    'file' => '/app/.env.example',
                    'success' => true,
                ],
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/move*' => Http::response([], 200),
        ]);

        // Create development directory structure
        $this->sandbox
            ->createFolder('/app/src', '755')
            ->createFolder('/app/tests', '755')
            ->createFolder('/app/vendor', '755')
            ->createFolder('/app/node_modules', '755')
            ->createFolder('/app/public', '755')
            ->createFolder('/app/storage/logs', '755')
            ->createFolder('/app/storage/cache', '777')
            ->createFolder('/app/storage/uploads', '777');

        // Create environment files
        $this->sandbox
            ->writeFile('/app/.env.example', "APP_ENV=production\nDEBUG=false\n")
            ->writeFile('/app/package.json', '{"name": "my-app", "version": "1.0.0"}')
            ->writeFile('/app/composer.json', '{"name": "my-app/app"}');

        // Configure for development
        $this->sandbox->replaceInFile('/app/.env.example', 'APP_ENV=production', 'APP_ENV=development');
        $this->sandbox->replaceInFile('/app/.env.example', 'DEBUG=false', 'DEBUG=true');

        // Copy to actual env file
        $this->sandbox->moveFile('/app/.env.example', '/app/.env');

        // Set proper permissions
        $this->sandbox
            ->setPermissions('/app/.env', '600', 'www-data', 'www-data')
            ->setPermissions('/app/storage', '755', 'www-data', 'www-data')
            ->setPermissions('/app/storage/cache', '777')
            ->setPermissions('/app/storage/uploads', '777');

        Http::assertSentCount(18); // Complex workflow with many operations
    });
});

describe('Backup and Deployment Workflow', function () {
    it('can prepare application for deployment', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/search?path=%2Fapp&pattern=.env' => Http::response([
                'files' => ['/app/.env'],
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/find*' => Http::response([
                [
                    'file' => '/app/src/debug.php',
                    'line' => 5,
                    'content' => 'echo "Debug mode enabled";',
                ],
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response([
                [
                    'file' => '/app/.env',
                    'success' => true,
                ],
                [
                    'file' => '/app/config.php',
                    'success' => true,
                ],
                [
                    'file' => '/app/settings.json',
                    'success' => true,
                ],
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response([], 200),
            '*/toolbox/sandbox-123/toolbox/files/move*' => Http::response([], 200),
        ]);

        // Find configuration files
        $configFiles = $this->sandbox->searchFiles('/app', '.env');
        expect($configFiles->getCount())->toBe(1);

        // Find debug statements
        $debugStatements = $this->sandbox->findInFiles('/app/src', 'echo.*[Dd]ebug');
        expect($debugStatements)->toHaveCount(1);

        // Prepare for production
        $productionFiles = ['/app/.env', '/app/config.php', '/app/settings.json'];

        // Disable debug mode
        $results = $this->sandbox->replaceInFiles(
            $productionFiles,
            'debug.*=.*true',
            'debug = false'
        );

        // Create backup directory
        $this->sandbox->createFolder('/app/backups', '755');

        // Set production permissions
        $this->sandbox
            ->setPermissions('/app/.env', '600', 'www-data', 'www-data')
            ->setPermissions('/app/config.php', '644', 'www-data', 'www-data')
            ->setPermissions('/app/logs', '755', 'www-data', 'www-data');

        // Move sensitive files to secure location
        $this->sandbox->moveFile('/app/backup.sql', '/app/backups/backup.sql');

        expect($results)->toHaveCount(3);
        foreach ($results as $result) {
            expect($result->isSuccess())->toBeTrue();
        }

        Http::assertSentCount(8); // search + find + replace + folder + 3 permissions + move
    });
});

describe('Error Handling in Complex Workflows', function () {
    it('gracefully handles partial failures in batch operations', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::sequence()
                ->push([], 200) // First folder succeeds
                ->push(['error' => 'Permission denied'], 403) // Second fails
                ->push([], 200), // Third succeeds
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
        ]);

        $successCount = 0;
        $errorCount = 0;

        try {
            $this->sandbox->createFolder('/app/allowed', '755');
            $successCount++;
        } catch (\Exception $e) {
            $errorCount++;
        }

        try {
            $this->sandbox->createFolder('/restricted/denied', '755');
            $successCount++;
        } catch (\Exception $e) {
            $errorCount++;
        }

        try {
            $this->sandbox->createFolder('/app/another', '755');
            $successCount++;
        } catch (\Exception $e) {
            $errorCount++;
        }

        // Continue with successful operations
        $this->sandbox->setPermissions('/app/allowed', '755');

        expect($successCount)->toBe(2);
        expect($errorCount)->toBe(1);

        Http::assertSentCount(4); // 3 folder attempts + 1 permission set
    });

    it('provides detailed error information for debugging', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response([
                [
                    'file' => '/app/success.txt',
                    'success' => true,
                ],
                [
                    'file' => '/app/readonly.txt',
                    'success' => false,
                    'error' => 'Permission denied: file is read-only',
                ],
                [
                    'file' => '/app/missing.txt',
                    'success' => false,
                    'error' => 'No such file or directory',
                ],
            ], 200),
        ]);

        $files = ['/app/success.txt', '/app/readonly.txt', '/app/missing.txt'];
        $results = $this->sandbox->replaceInFiles($files, 'old', 'new');

        $successful = array_filter($results, fn ($r) => $r->isSuccess());
        $failed = array_filter($results, fn ($r) => ! $r->isSuccess());

        expect($successful)->toHaveCount(1);
        expect($failed)->toHaveCount(2);

        // Verify detailed error messages
        $readonlyError = array_values(array_filter($failed, fn ($r) => str_contains($r->file, 'readonly')))[0];
        $missingError = array_values(array_filter($failed, fn ($r) => str_contains($r->file, 'missing')))[0];

        expect($readonlyError->error)->toBe('Permission denied: file is read-only');
        expect($missingError->error)->toBe('No such file or directory');
    });
});
