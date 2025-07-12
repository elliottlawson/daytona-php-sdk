<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DaytonaSandbox;
use ElliottLawson\Daytona\DTOs\CommandResponse;
use Illuminate\Support\Facades\Http;

it('can execute commands in a sandbox', function () {
    $mockResponse = [
        'output' => "Hello from sandbox\n",
        'exit_code' => 0,
        'error' => '',
    ];
    
    Http::fake([
        '*/sandboxes/sandbox-123/exec' => Http::response($mockResponse, 200),
    ]);
    
    $client = new DaytonaClient('https://api.example.com', 'test-api-key');
    $sandbox = new DaytonaSandbox($client, 'sandbox-123');
    
    $result = $sandbox->exec('echo "Hello from sandbox"');
    
    expect($result)->toBeInstanceOf(CommandResponse::class)
        ->and($result->output)->toBe("Hello from sandbox\n")
        ->and($result->exitCode)->toBe(0)
        ->and($result->isSuccess())->toBeTrue();
});

it('can read file contents from sandbox', function () {
    $mockResponse = [
        'content' => 'console.log("Hello World");',
        'path' => '/workspace/index.js',
    ];
    
    Http::fake([
        '*/sandboxes/sandbox-123/files*' => Http::response($mockResponse, 200),
    ]);
    
    $client = new DaytonaClient('https://api.example.com', 'test-api-key');
    $sandbox = new DaytonaSandbox($client, 'sandbox-123');
    
    $content = $sandbox->readFile('/workspace/index.js');
    
    expect($content)->toBe('console.log("Hello World");');
});

it('can write file contents to sandbox', function () {
    Http::fake([
        '*/sandboxes/sandbox-123/files*' => Http::response(['success' => true], 200),
    ]);
    
    $client = new DaytonaClient('https://api.example.com', 'test-api-key');
    $sandbox = new DaytonaSandbox($client, 'sandbox-123');
    
    $result = $sandbox->writeFile('/workspace/test.txt', 'Test content');
    
    expect($result)->toBeTrue();
});

it('can list directory contents', function () {
    $mockResponse = [
        'files' => [
            ['name' => 'index.js', 'type' => 'file', 'size' => 1024],
            ['name' => 'src', 'type' => 'directory', 'size' => 0],
            ['name' => 'package.json', 'type' => 'file', 'size' => 512],
        ],
    ];
    
    Http::fake([
        '*/sandboxes/sandbox-123/ls*' => Http::response($mockResponse, 200),
    ]);
    
    $client = new DaytonaClient('https://api.example.com', 'test-api-key');
    $sandbox = new DaytonaSandbox($client, 'sandbox-123');
    
    $files = $sandbox->listDirectory('/workspace');
    
    expect($files)->toBeArray()
        ->and($files)->toHaveCount(3)
        ->and($files[0]->name)->toBe('index.js')
        ->and($files[0]->type)->toBe('file');
});