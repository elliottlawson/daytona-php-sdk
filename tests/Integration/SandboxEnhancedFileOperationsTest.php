<?php

use ElliottLawson\Daytona\DTOs\FileInfo;
use ElliottLawson\Daytona\DTOs\FilePermissionsParams;
use ElliottLawson\Daytona\DTOs\ReplaceResult;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use ElliottLawson\Daytona\DTOs\SearchFilesResponse;
use ElliottLawson\Daytona\DTOs\SearchMatch;
use ElliottLawson\Daytona\Exceptions\ApiException;
use ElliottLawson\Daytona\Exceptions\FileSystemException;
use Tests\Integration\SandboxTestHelper;

uses(SandboxTestHelper::class);

beforeEach(function () {
    $this->setupClient();
});

afterEach(function () {
    $this->cleanupSandboxes();
});

it('can test which enhanced file operations are available in the API', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    /**
     * Testing which enhanced file operations are available in the current API
     */

    echo "\n=== Testing Enhanced File Operations ===\n";

    // Create a test directory using exec since createFolder might not work yet
    $sandbox->exec('mkdir -p /home/daytona/test-dir /home/daytona/test-dir2');
    
    // Create test files
    $sandbox->writeFile('/home/daytona/test-dir/file1.txt', 'Content of file 1');
    $sandbox->writeFile('/home/daytona/test-dir/file2.txt', 'Content of file 2');
    $sandbox->writeFile('/home/daytona/test-dir/config.json', '{"version": "1.0.0", "debug": false}');
    $sandbox->writeFile('/home/daytona/test-dir/script.sh', '#!/bin/bash\necho "Hello World"');

    // Test 1: createFolder
    try {
        $sandbox->createFolder('/home/daytona/created-folder', '755');
        echo "✓ createFolder works\n";
        expect($sandbox->fileExists('/home/daytona/created-folder'))->toBeTrue();
    } catch (\Exception $e) {
        echo "✗ createFolder: " . substr($e->getMessage(), 0, 100) . "...\n";
    }

    // Test 2: moveFile
    try {
        $sandbox->moveFile('/home/daytona/test-dir/file1.txt', '/home/daytona/test-dir/renamed.txt');
        echo "✓ moveFile works\n";
        expect($sandbox->fileExists('/home/daytona/test-dir/renamed.txt'))->toBeTrue();
        expect($sandbox->fileExists('/home/daytona/test-dir/file1.txt'))->toBeFalse();
    } catch (\Exception $e) {
        echo "✗ moveFile: " . substr($e->getMessage(), 0, 100) . "...\n";
    }

    // Test 3: getFileDetails
    try {
        $details = $sandbox->getFileDetails('/home/daytona/test-dir/config.json');
        echo "✓ getFileDetails works\n";
        expect($details)->toBeInstanceOf(FileInfo::class);
        expect($details->name)->toBe('config.json');
        expect($details->size)->toBeGreaterThan(0);
        // Check if enhanced fields are present
        if ($details->mode !== null) echo "  - Has mode field: {$details->mode}\n";
        if ($details->owner !== null) echo "  - Has owner field: {$details->owner}\n";
        if ($details->group !== null) echo "  - Has group field: {$details->group}\n";
    } catch (\Exception $e) {
        echo "✗ getFileDetails: " . substr($e->getMessage(), 0, 100) . "...\n";
    }

    // Test 4: setPermissions
    try {
        $sandbox->setPermissions('/home/daytona/test-dir/script.sh', mode: '755');
        echo "✓ setPermissions works\n";
    } catch (\Exception $e) {
        echo "✗ setPermissions: " . substr($e->getMessage(), 0, 100) . "...\n";
    }

    // Test 5: searchFiles
    try {
        $sandbox->writeFile('/home/daytona/test-dir/search1.txt', 'test');
        $sandbox->writeFile('/home/daytona/test-dir/search2.txt', 'test');
        $result = $sandbox->searchFiles('/home/daytona/test-dir', '*.txt');
        echo "✓ searchFiles works - found {$result->getCount()} files\n";
        expect($result)->toBeInstanceOf(SearchFilesResponse::class);
        expect($result->getCount())->toBeGreaterThan(0);
    } catch (\Exception $e) {
        echo "✗ searchFiles: " . substr($e->getMessage(), 0, 100) . "...\n";
    }

    // Test 6: findInFiles
    try {
        $sandbox->writeFile('/home/daytona/test-dir/todo.txt', "TODO: Fix this\nTODO: Do that");
        $matches = $sandbox->findInFiles('/home/daytona/test-dir', 'TODO:');
        echo "✓ findInFiles works - found " . count($matches) . " matches\n";
        expect($matches)->toBeArray();
        expect(count($matches))->toBeGreaterThan(0);
        if (count($matches) > 0) {
            expect($matches[0])->toBeInstanceOf(SearchMatch::class);
        }
    } catch (\Exception $e) {
        echo "✗ findInFiles: " . substr($e->getMessage(), 0, 100) . "...\n";
    }

    // Test 7: replaceInFiles
    try {
        $sandbox->writeFile('/home/daytona/test-dir/version.txt', 'version: 1.0.0');
        $results = $sandbox->replaceInFiles(
            ['/home/daytona/test-dir/version.txt'],
            'version: 1.0.0',
            'version: 2.0.0'
        );
        echo "✓ replaceInFiles works\n";
        expect($results)->toBeArray();
        expect($results[0])->toBeInstanceOf(ReplaceResult::class);
        expect($results[0]->isSuccess())->toBeTrue();
        expect($sandbox->readFile('/home/daytona/test-dir/version.txt'))->toContain('version: 2.0.0');
    } catch (\Exception $e) {
        echo "✗ replaceInFiles: " . substr($e->getMessage(), 0, 100) . "...\n";
    }

    echo "\n=== Test Complete ===\n";
});

it('handles edge cases and errors for enhanced file operations', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    // Test 1: Search in non-existent directory - might return empty results
    $result = $sandbox->searchFiles('/home/daytona/fake-dir', '*.txt');
    expect($result)->toBeInstanceOf(SearchFilesResponse::class);
    expect($result->isEmpty())->toBeTrue();
    expect($result->getCount())->toBe(0);

    // Test 2: Find in files with non-existent path - might return empty array
    $matches = $sandbox->findInFiles('/home/daytona/fake-dir', 'pattern');
    expect($matches)->toBeArray();
    expect($matches)->toBeEmpty();

    // Test 3: Try to move non-existent file - this should throw
    expect(fn() => $sandbox->moveFile('/home/daytona/fake-file.txt', '/home/daytona/new-name.txt'))
        ->toThrow(\Exception::class);

    // Test 4: Try to get details of non-existent file - this should throw
    expect(fn() => $sandbox->getFileDetails('/home/daytona/does-not-exist.txt'))
        ->toThrow(\Exception::class);

    // Test 5: Replace in non-existent files - returns error results
    $fakeFiles = ['/home/daytona/fake1.txt', '/home/daytona/fake2.txt'];
    $results = $sandbox->replaceInFiles($fakeFiles, 'pattern', 'replacement');
    
    expect($results)->toBeArray();
    expect(count($results))->toBe(2);
    
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(ReplaceResult::class);
        expect($result->isSuccess())->toBeFalse();
        expect($result->error)->not->toBeEmpty();
    }

    // Test 6: Create nested directories
    $sandbox->createFolder('/home/daytona/level1/level2/level3', '755');
    expect($sandbox->fileExists('/home/daytona/level1/level2/level3'))->toBeTrue();

    // Test 7: Move directory
    $sandbox->exec('mkdir -p /home/daytona/source-dir');
    $sandbox->writeFile('/home/daytona/source-dir/file.txt', 'content');
    $sandbox->moveFile('/home/daytona/source-dir', '/home/daytona/dest-dir');
    
    expect($sandbox->fileExists('/home/daytona/dest-dir'))->toBeTrue();
    expect($sandbox->fileExists('/home/daytona/dest-dir/file.txt'))->toBeTrue();
    expect($sandbox->fileExists('/home/daytona/source-dir'))->toBeFalse();

    // Test 8: Search with complex patterns
    $sandbox->writeFile('/home/daytona/file.txt', 'text');
    $sandbox->writeFile('/home/daytona/file.log', 'log');
    $sandbox->writeFile('/home/daytona/test.txt', 'test');
    
    $txtFiles = $sandbox->searchFiles('/home/daytona', '*.txt');
    expect($txtFiles->getCount())->toBe(3); // includes dest-dir/file.txt from move test
    
    $filePattern = $sandbox->searchFiles('/home/daytona', 'file.*');
    expect($filePattern->getCount())->toBe(3); // file.txt, file.log, and dest-dir/file.txt

    // Test 9: Find with regex patterns
    $sandbox->writeFile('/home/daytona/errors.log', "Error: Connection failed\nWarning: Timeout\nError: Invalid input");
    
    $errorMatches = $sandbox->findInFiles('/home/daytona', 'Error:');
    expect(count($errorMatches))->toBe(2);

    // Test 10: Set permissions on directory
    $sandbox->exec('mkdir -p /home/daytona/perm-test');
    $sandbox->setPermissions('/home/daytona/perm-test', mode: '777');
    
    // Get directory details to verify (if supported)
    $dirInfo = $sandbox->getFileDetails('/home/daytona/perm-test');
    expect($dirInfo->isDirectory)->toBeTrue();
});

it('tests enhanced file operations', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    // Create directories with different permissions
    $sandbox->createFolder('/home/daytona/test-dir', '755');
    $sandbox->createFolder('/home/daytona/public-dir', '777');
    $sandbox->createFolder('/home/daytona/private-dir', '700');
    
    // Verify directories were created
    $listing = $sandbox->listDirectory('/home/daytona');
    $dirNames = array_map(fn($file) => $file->name, $listing->files);
    expect($dirNames)->toContain('test-dir');
    expect($dirNames)->toContain('public-dir');
    expect($dirNames)->toContain('private-dir');

    // Create test files for operations
    $sandbox->writeFile('/home/daytona/test-dir/file1.txt', 'Content of file 1');
    $sandbox->writeFile('/home/daytona/test-dir/file2.txt', 'Content of file 2');
    $sandbox->writeFile('/home/daytona/test-dir/config.json', '{"version": "1.0.0", "debug": false}');
    $sandbox->writeFile('/home/daytona/test-dir/script.sh', '#!/bin/bash\necho "Hello World"');
    
    // Move/rename files
    $sandbox->moveFile('/home/daytona/test-dir/file1.txt', '/home/daytona/test-dir/renamed-file1.txt');
    expect($sandbox->fileExists('/home/daytona/test-dir/renamed-file1.txt'))->toBeTrue();
    expect($sandbox->fileExists('/home/daytona/test-dir/file1.txt'))->toBeFalse();
    
    // Move file to different directory
    $sandbox->moveFile('/home/daytona/test-dir/file2.txt', '/home/daytona/public-dir/moved-file2.txt');
    expect($sandbox->fileExists('/home/daytona/public-dir/moved-file2.txt'))->toBeTrue();
    expect($sandbox->fileExists('/home/daytona/test-dir/file2.txt'))->toBeFalse();

    // Get detailed file information
    $fileInfo = $sandbox->getFileDetails('/home/daytona/test-dir/config.json');
    expect($fileInfo)->toBeInstanceOf(FileInfo::class);
    expect($fileInfo->name)->toBe('config.json');
    expect($fileInfo->size)->toBeGreaterThan(0);
    expect($fileInfo->isDirectory)->toBeFalse();

    // Set file permissions
    $permissions = new FilePermissionsParams(
        mode: '755',
        owner: null,
        group: null
    );
    $sandbox->setFilePermissions('/home/daytona/test-dir/script.sh', $permissions);
    
    // Use convenience method
    $sandbox->setPermissions('/home/daytona/test-dir/config.json', mode: '644');

    // Search files by pattern
    $sandbox->writeFile('/home/daytona/test-dir/app.js', 'console.log("app");');
    $sandbox->writeFile('/home/daytona/test-dir/test.js', 'console.log("test");');
    $sandbox->writeFile('/home/daytona/test-dir/data.json', '{"key": "value"}');
    
    $searchResult = $sandbox->searchFiles('/home/daytona/test-dir', '*.js');
    expect($searchResult)->toBeInstanceOf(SearchFilesResponse::class);
    expect($searchResult->getCount())->toBe(2);
    expect($searchResult->files)->toContain('/home/daytona/test-dir/app.js');
    expect($searchResult->files)->toContain('/home/daytona/test-dir/test.js');

    // Find text in files
    $sandbox->writeFile('/home/daytona/test-dir/todo.txt', "TODO: Implement feature A\nTODO: Fix bug B\nDone: Task C");
    $sandbox->writeFile('/home/daytona/test-dir/notes.txt', "Remember: Update docs\nTODO: Review code");
    
    $todoMatches = $sandbox->findInFiles('/home/daytona/test-dir', 'TODO:');
    expect($todoMatches)->toBeArray();
    expect(count($todoMatches))->toBe(3); // 3 TODO matches across files
    
    foreach ($todoMatches as $match) {
        expect($match)->toBeInstanceOf(SearchMatch::class);
        expect($match->file)->toBeString();
        expect($match->line)->toBeInt();
        expect($match->content)->toContain('TODO:');
    }

    // Replace text in files
    $filesToUpdate = [
        '/home/daytona/test-dir/config.json',
        '/home/daytona/test-dir/data.json'
    ];
    
    $replaceResults = $sandbox->replaceInFiles(
        files: $filesToUpdate,
        pattern: '"version": "1.0.0"',
        newValue: '"version": "1.1.0"'
    );
    
    expect($replaceResults)->toBeArray();
    expect(count($replaceResults))->toBe(2);
    
    foreach ($replaceResults as $result) {
        expect($result)->toBeInstanceOf(ReplaceResult::class);
        if ($result->file === '/home/daytona/test-dir/config.json') {
            expect($result->isSuccess())->toBeTrue();
        }
    }
    
    // Verify replacement worked
    $updatedConfig = $sandbox->readFile('/home/daytona/test-dir/config.json');
    expect($updatedConfig)->toContain('"version": "1.1.0"');

    // Replace in single file convenience method
    $singleResult = $sandbox->replaceInFile(
        file: '/home/daytona/test-dir/todo.txt',
        pattern: 'TODO:',
        newValue: 'TASK:'
    );
    
    expect($singleResult)->toBeInstanceOf(ReplaceResult::class);
    expect($singleResult->isSuccess())->toBeTrue();
    
    // Verify replacement
    $updatedTodo = $sandbox->readFile('/home/daytona/test-dir/todo.txt');
    expect($updatedTodo)->toContain('TASK:');
    expect($updatedTodo)->not->toContain('TODO:');
});