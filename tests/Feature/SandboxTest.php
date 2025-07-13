<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\Sandbox;
use ElliottLawson\Daytona\DTOs\CommandResponse;
use ElliottLawson\Daytona\DTOs\Config;
use Illuminate\Support\Facades\Http;

it('can execute commands in a sandbox', function () {
    $mockResponse = [
        'exitCode' => 0,
        'stdout' => "Hello from sandbox\n",
        'stderr' => '',
    ];
    
    Http::fake([
        '*/toolbox/sandbox-123/toolbox/process/execute' => Http::response($mockResponse, 200),
    ]);
    
    $config = new Config(
        apiKey: 'test-api-key',
        apiUrl: 'https://api.example.com',
        organizationId: 'test-org'
    );
    $client = new DaytonaClient($config);
    $sandbox = new Sandbox('sandbox-123', $client);
    
    $result = $sandbox->exec('echo "Hello from sandbox"');
    
    expect($result)->toBeInstanceOf(CommandResponse::class)
        ->and($result->output)->toBe("Hello from sandbox\n")
        ->and($result->exitCode)->toBe(0)
        ->and($result->isSuccessful())->toBeTrue();
});

it('can read file contents from sandbox', function () {
    $mockContent = 'console.log("Hello World");';
    
    Http::fake([
        '*/toolbox/sandbox-123/toolbox/files/download*' => Http::response($mockContent, 200),
    ]);
    
    $config = new Config(
        apiKey: 'test-api-key',
        apiUrl: 'https://api.example.com',
        organizationId: 'test-org'
    );
    $client = new DaytonaClient($config);
    $sandbox = new Sandbox('sandbox-123', $client);
    
    $content = $sandbox->readFile('/workspace/index.js');
    
    expect($content)->toBe('console.log("Hello World");');
});

it('can write file contents to sandbox', function () {
    Http::fake([
        '*/toolbox/sandbox-123/toolbox/files/upload*' => Http::response(['success' => true], 200),
    ]);
    
    $config = new Config(
        apiKey: 'test-api-key',
        apiUrl: 'https://api.example.com',
        organizationId: 'test-org'
    );
    $client = new DaytonaClient($config);
    $sandbox = new Sandbox('sandbox-123', $client);
    
    // writeFile returns void, so we just need to ensure no exception is thrown
    $sandbox->writeFile('/workspace/test.txt', 'Test content');
    
    expect(true)->toBeTrue(); // Test passes if no exception
});

it('can list directory contents', function () {
    $mockResponse = [
        'files' => [
            ['name' => 'index.js', 'path' => '/workspace/index.js', 'isDirectory' => false, 'size' => 1024],
            ['name' => 'src', 'path' => '/workspace/src', 'isDirectory' => true, 'size' => 0],
            ['name' => 'package.json', 'path' => '/workspace/package.json', 'isDirectory' => false, 'size' => 512],
        ],
    ];
    
    Http::fake([
        '*/toolbox/sandbox-123/toolbox/files*' => Http::response($mockResponse, 200),
    ]);
    
    $config = new Config(
        apiKey: 'test-api-key',
        apiUrl: 'https://api.example.com',
        organizationId: 'test-org'
    );
    $client = new DaytonaClient($config);
    $sandbox = new Sandbox('sandbox-123', $client);
    
    $listing = $sandbox->listDirectory('/workspace');
    
    expect($listing)->toBeInstanceOf(\ElliottLawson\Daytona\DTOs\DirectoryListingResponse::class)
        ->and($listing->files)->toHaveCount(3)
        ->and($listing->files[0]->name)->toBe('index.js')
        ->and($listing->files[0]->isDirectory)->toBe(false);
});