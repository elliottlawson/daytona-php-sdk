<?php

use ElliottLawson\Daytona\DTOs\DirectoryListingResponse;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use ElliottLawson\Daytona\Exceptions\ApiException;
use Tests\Integration\SandboxTestHelper;

uses(SandboxTestHelper::class);

beforeEach(function () {
    $this->setupClient();
});

afterEach(function () {
    $this->cleanupSandboxes();
});

it('can perform comprehensive file operations in a sandbox', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    /**
     * File Operation Test Coverage:
     * 1. writeFile() - Create new files
     * 2. fileExists() - Check file existence  
     * 3. readFile() - Read file contents
     * 4. writeFile() - Overwrite existing files
     * 5. writeFile() - Create files in nested directories
     * 6. Different file types (JSON, text, empty)
     * 7. listDirectory() - List directory contents
     * 8. deleteFile() - Delete files
     * 9. Special characters in content
     * 10. Binary-like content
     */

    // Test 1: Write a file
    $filePath = '/home/daytona/test-file.txt';
    $fileContent = 'Hello from Daytona PHP SDK!';
    
    $sandbox->writeFile(
        path: $filePath,
        content: $fileContent
    );

    // Test 2: Check file exists
    expect($sandbox->fileExists($filePath))->toBeTrue();
    expect($sandbox->fileExists('/home/daytona/nonexistent.txt'))->toBeFalse();

    // Test 3: Read the file back
    $readContent = $sandbox->readFile($filePath);
    expect($readContent)->toBe($fileContent);

    // Test 4: Overwrite existing file
    $newContent = 'Updated content!';
    $sandbox->writeFile(
        path: $filePath,
        content: $newContent
    );
    expect($sandbox->readFile($filePath))->toBe($newContent);

    // Test 5: Write files in subdirectories (test directory creation)
    $nestedPath = '/home/daytona/nested/dir/file.txt';
    $sandbox->writeFile(
        path: $nestedPath,
        content: 'Nested file content'
    );
    expect($sandbox->fileExists($nestedPath))->toBeTrue();
    expect($sandbox->readFile($nestedPath))->toBe('Nested file content');

    // Test 6: Write different file types
    $jsonPath = '/home/daytona/data.json';
    $jsonContent = json_encode(['key' => 'value', 'nested' => ['array' => [1, 2, 3]]]);
    $sandbox->writeFile(
        path: $jsonPath,
        content: $jsonContent
    );
    expect($sandbox->readFile($jsonPath))->toBe($jsonContent);

    // Test 7: Write empty file
    $emptyPath = '/home/daytona/empty.txt';
    $sandbox->writeFile(
        path: $emptyPath,
        content: ''
    );
    expect($sandbox->fileExists($emptyPath))->toBeTrue();
    expect($sandbox->readFile($emptyPath))->toBe('');

    // Test 8: List directory contents
    $listing = $sandbox->listDirectory('/home/daytona');
    expect($listing)->toBeInstanceOf(DirectoryListingResponse::class);
    
    $fileNames = array_map(fn($file) => $file->name, $listing->files);
    expect($fileNames)->toContain('test-file.txt');
    expect($fileNames)->toContain('data.json');
    expect($fileNames)->toContain('empty.txt');
    expect($fileNames)->toContain('nested'); // Should see the directory

    // Test 9: List nested directory
    $nestedListing = $sandbox->listDirectory('/home/daytona/nested/dir');
    $nestedFileNames = array_map(fn($file) => $file->name, $nestedListing->files);
    expect($nestedFileNames)->toContain('file.txt');

    // Test 10: Delete file
    $sandbox->deleteFile($filePath);
    expect($sandbox->fileExists($filePath))->toBeFalse();
    
    // Verify file is gone from listing
    $listingAfterDelete = $sandbox->listDirectory('/home/daytona');
    $fileNamesAfterDelete = array_map(fn($file) => $file->name, $listingAfterDelete->files);
    expect($fileNamesAfterDelete)->not->toContain('test-file.txt');

    // Test 11: Try to read deleted file (should throw exception)
    expect(fn() => $sandbox->readFile($filePath))
        ->toThrow(ApiException::class);

    // Test 12: Delete file in subdirectory
    $sandbox->deleteFile($nestedPath);
    expect($sandbox->fileExists($nestedPath))->toBeFalse();

    // Test 13: Write file with special characters in content
    $specialPath = '/home/daytona/special-chars.txt';
    $specialContent = "Special chars: \n\t\r \"quotes\" 'apostrophes' \\backslash € £ 中文 🚀";
    $sandbox->writeFile(
        path: $specialPath,
        content: $specialContent
    );
    expect($sandbox->readFile($specialPath))->toBe($specialContent);

    // Test 14: Write binary-like content (base64)
    $binaryPath = '/home/daytona/binary.dat';
    $binaryContent = base64_encode(random_bytes(100));
    $sandbox->writeFile(
        path: $binaryPath,
        content: $binaryContent
    );
    expect($sandbox->readFile($binaryPath))->toBe($binaryContent);
});

it('handles file operation edge cases and errors', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    // Test 1: Try to read non-existent file
    expect(fn() => $sandbox->readFile('/home/daytona/does-not-exist.txt'))
        ->toThrow(ApiException::class);

    // Test 2: Try to delete non-existent file (API might or might not throw)
    try {
        $sandbox->deleteFile('/home/daytona/does-not-exist.txt');
        // If no exception, that's also acceptable behavior
        expect(true)->toBeTrue();
    } catch (ApiException $e) {
        // Exception is also acceptable
        expect($e)->toBeInstanceOf(ApiException::class);
    }

    // Test 3: Write file with very long name
    $longNamePath = '/home/daytona/' . str_repeat('a', 100) . '.txt';
    $sandbox->writeFile(
        path: $longNamePath,
        content: 'Long filename test'
    );
    expect($sandbox->fileExists($longNamePath))->toBeTrue();

    // Test 4: Write large content (1MB)
    $largeContent = str_repeat('Lorem ipsum dolor sit amet. ', 35000); // ~1MB
    $largePath = '/home/daytona/large-file.txt';
    $sandbox->writeFile(
        path: $largePath,
        content: $largeContent
    );
    expect($sandbox->readFile($largePath))->toBe($largeContent);

    // Test 5: File paths with spaces and special characters
    $specialNamePath = '/home/daytona/file with spaces & special-chars!.txt';
    $sandbox->writeFile(
        path: $specialNamePath,
        content: 'Special filename test'
    );
    expect($sandbox->fileExists($specialNamePath))->toBeTrue();
    expect($sandbox->readFile($specialNamePath))->toBe('Special filename test');

    // Test 6: List empty directory (create a new directory by writing a file then deleting it)
    $tempDirFile = '/home/daytona/emptydir/temp.txt';
    $sandbox->writeFile(
        path: $tempDirFile,
        content: 'temp'
    );
    $sandbox->deleteFile($tempDirFile);
    
    // The directory might still exist, try to list it
    try {
        $emptyListing = $sandbox->listDirectory('/home/daytona/emptydir');
        expect($emptyListing->files)->toBeArray();
    } catch (\Exception $e) {
        // Directory might not exist after file deletion, that's ok
        expect(true)->toBeTrue();
    }

    // Test 7: Try to list non-existent directory
    expect(fn() => $sandbox->listDirectory('/home/daytona/totally-fake-dir'))
        ->toThrow(ApiException::class);

    // Test 8: Multiple sequential writes to same file
    $multiWritePath = '/home/daytona/multi-write.txt';
    for ($i = 1; $i <= 5; $i++) {
        $sandbox->writeFile(
            path: $multiWritePath,
            content: "Write number {$i}"
        );
        expect($sandbox->readFile($multiWritePath))->toBe("Write number {$i}");
    }

    // Test 9: Write files with different extensions
    $extensions = [
        'test.php' => '<?php echo "Hello";',
        'test.js' => 'console.log("Hello");',
        'test.py' => 'print("Hello")',
        'test.sh' => '#!/bin/bash\necho "Hello"',
        'test.md' => '# Hello\n\nMarkdown content',
        'test.xml' => '<?xml version="1.0"?><root>Hello</root>',
    ];

    foreach ($extensions as $filename => $content) {
        $path = "/home/daytona/{$filename}";
        $sandbox->writeFile(path: $path, content: $content);
        expect($sandbox->readFile($path))->toBe($content);
    }
});

it('demonstrates method chaining support for file operations', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    // Test method chaining which was added in the enhanced file operations
    $sandbox
        ->writeFile('/home/daytona/chain1.txt', 'First file')
        ->writeFile('/home/daytona/chain2.txt', 'Second file')  
        ->writeFile('/home/daytona/chain3.txt', 'Third file')
        ->deleteFile('/home/daytona/chain2.txt');
    
    expect($sandbox->fileExists('/home/daytona/chain1.txt'))->toBeTrue();
    expect($sandbox->fileExists('/home/daytona/chain2.txt'))->toBeFalse();
    expect($sandbox->fileExists('/home/daytona/chain3.txt'))->toBeTrue();
    
    // Verify content
    expect($sandbox->readFile('/home/daytona/chain1.txt'))->toBe('First file');
    expect($sandbox->readFile('/home/daytona/chain3.txt'))->toBe('Third file');
});

it('handles large file operations', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    
    // Test 1: Write and read a 10MB file
    $tenMbSize = 10 * 1024 * 1024; // 10MB in bytes
    $largeContent = str_repeat('A', $tenMbSize);
    $largePath = '/home/daytona/large-10mb.txt';
    
    // Measure write performance
    $writeStart = microtime(true);
    $sandbox->writeFile(
        path: $largePath,
        content: $largeContent
    );
    $writeTime = microtime(true) - $writeStart;
    
    // Verify file exists
    expect($sandbox->fileExists($largePath))->toBeTrue();
    
    // Measure read performance
    $readStart = microtime(true);
    $readContent = $sandbox->readFile($largePath);
    $readTime = microtime(true) - $readStart;
    
    // Verify content integrity
    expect(strlen($readContent))->toBe($tenMbSize);
    expect($readContent)->toBe($largeContent);
    
    // Log performance metrics (these are informational, not assertions)
    echo "\n10MB File Performance:";
    echo "\n  Write time: " . round($writeTime, 3) . " seconds";
    echo "\n  Read time: " . round($readTime, 3) . " seconds";
    
    // Clean up large file
    $sandbox->deleteFile($largePath);
});

it('handles files with many lines', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    
    // Test 2: File with many lines (100,000 lines)
    $numberOfLines = 100000;
    $lines = [];
    for ($i = 1; $i <= $numberOfLines; $i++) {
        $lines[] = "Line {$i}: This is test content for line number {$i} with some padding text to make it realistic.";
    }
    $manyLinesContent = implode("\n", $lines);
    $manyLinesPath = '/home/daytona/many-lines.txt';
    
    // Write file with many lines
    $writeStart = microtime(true);
    $sandbox->writeFile(
        path: $manyLinesPath,
        content: $manyLinesContent
    );
    $writeTime = microtime(true) - $writeStart;
    
    // Read file back
    $readStart = microtime(true);
    $readContent = $sandbox->readFile($manyLinesPath);
    $readTime = microtime(true) - $readStart;
    
    // Verify content integrity
    expect($readContent)->toBe($manyLinesContent);
    
    // Verify line count
    $readLines = explode("\n", $readContent);
    expect(count($readLines))->toBe($numberOfLines);
    
    // Spot check some lines
    expect($readLines[0])->toContain('Line 1:');
    expect($readLines[99999])->toContain('Line 100000:');
    
    // Log performance
    echo "\n100K Lines File Performance:";
    echo "\n  Write time: " . round($writeTime, 3) . " seconds";
    echo "\n  Read time: " . round($readTime, 3) . " seconds";
    echo "\n  File size: " . round(strlen($manyLinesContent) / 1024 / 1024, 2) . " MB";
    
    // Clean up
    $sandbox->deleteFile($manyLinesPath);
});

it('tests file operation performance with various sizes', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    
    // Test 3: Performance testing with different file sizes
    $testSizes = [
        '1KB' => 1024,
        '100KB' => 100 * 1024,
        '1MB' => 1024 * 1024,
        '5MB' => 5 * 1024 * 1024,
    ];
    
    $performanceResults = [];
    
    foreach ($testSizes as $label => $sizeInBytes) {
        $content = str_repeat('X', $sizeInBytes);
        $path = "/home/daytona/perf-test-{$label}.txt";
        
        // Measure write performance
        $writeStart = microtime(true);
        $sandbox->writeFile(path: $path, content: $content);
        $writeTime = microtime(true) - $writeStart;
        
        // Measure read performance
        $readStart = microtime(true);
        $readContent = $sandbox->readFile($path);
        $readTime = microtime(true) - $readStart;
        
        // Verify content
        expect(strlen($readContent))->toBe($sizeInBytes);
        
        // Measure delete performance
        $deleteStart = microtime(true);
        $sandbox->deleteFile($path);
        $deleteTime = microtime(true) - $deleteStart;
        
        $performanceResults[$label] = [
            'write' => $writeTime,
            'read' => $readTime,
            'delete' => $deleteTime,
        ];
    }
    
    // Log performance summary
    echo "\nFile Operation Performance Summary:";
    foreach ($performanceResults as $size => $times) {
        echo "\n{$size}:";
        echo "\n  Write: " . round($times['write'], 4) . "s";
        echo "\n  Read: " . round($times['read'], 4) . "s";
        echo "\n  Delete: " . round($times['delete'], 4) . "s";
    }
    
    // Basic performance expectations (very lenient to account for network/system variations)
    // Even large files should complete within reasonable time
    foreach ($performanceResults as $size => $times) {
        expect($times['write'])->toBeLessThan(30); // 30 seconds max for write
        expect($times['read'])->toBeLessThan(30);  // 30 seconds max for read
        expect($times['delete'])->toBeLessThan(10); // 10 seconds max for delete
    }
});

it('handles edge cases with large files', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    
    // Test: Large file with mixed content types
    $mixedContent = '';
    
    // Add various content types
    $mixedContent .= str_repeat('Regular text content. ', 10000); // Text
    $mixedContent .= "\n\n" . json_encode(array_fill(0, 1000, ['key' => 'value'])); // JSON
    $mixedContent .= "\n\n" . base64_encode(random_bytes(50000)); // Base64
    $mixedContent .= "\n\n" . str_repeat("Special chars: € £ ¥ § ¶ • ª º « » ¿ ¡\n", 1000); // Unicode
    
    $mixedPath = '/home/daytona/large-mixed-content.txt';
    
    // Write mixed content file
    $sandbox->writeFile(
        path: $mixedPath,
        content: $mixedContent
    );
    
    // Read and verify
    $readContent = $sandbox->readFile($mixedPath);
    expect($readContent)->toBe($mixedContent);
    
    // Test: Multiple large files in same directory
    $largeFilesDir = '/home/daytona/large-files';
    for ($i = 1; $i <= 3; $i++) {
        $content = str_repeat("File {$i} content. ", 100000); // ~1.7MB each
        $sandbox->writeFile(
            path: "{$largeFilesDir}/large-{$i}.txt",
            content: $content
        );
    }
    
    // List directory with large files
    $listing = $sandbox->listDirectory($largeFilesDir);
    expect(count($listing->files))->toBe(3);
    
    // Verify all files exist
    for ($i = 1; $i <= 3; $i++) {
        expect($sandbox->fileExists("{$largeFilesDir}/large-{$i}.txt"))->toBeTrue();
    }
    
    // Clean up
    $sandbox->deleteFile($mixedPath);
    for ($i = 1; $i <= 3; $i++) {
        $sandbox->deleteFile("{$largeFilesDir}/large-{$i}.txt");
    }
});