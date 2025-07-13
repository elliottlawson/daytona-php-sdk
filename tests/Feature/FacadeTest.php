<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use ElliottLawson\Daytona\Facades\Daytona;
use ElliottLawson\Daytona\Sandbox;
use Illuminate\Support\Facades\Http;

it('can use Daytona facade', function () {
    expect(Daytona::getFacadeRoot())->toBeInstanceOf(DaytonaClient::class);
});

it('can create sandbox using facade', function () {
    Http::fake([
        '*/sandbox' => Http::response([
            'id' => 'facade-sandbox-123',
            'organizationId' => 'org-123',
            'state' => 'started',
            'target' => 'us',
            'snapshot' => 'php-8.3',
            'user' => 'daytona',
            'cpu' => 1,
            'memory' => 2,
            'disk' => 5,
            'createdAt' => now()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ], 201),
    ]);

    $sandbox = Daytona::createSandbox(new SandboxCreateParameters(
        language: 'php',
        snapshot: 'php-8.3',
    ));

    expect($sandbox)->toBeInstanceOf(Sandbox::class);
    expect($sandbox->getId())->toBe('facade-sandbox-123');
    expect($sandbox->getState())->toBe('started');
});

it('can execute commands using facade', function () {
    Http::fake([
        '*/toolbox/*/toolbox/process/execute' => Http::response([
            'exitCode' => 0,
            'stdout' => 'PHP 8.3.0',
            'stderr' => '',
        ], 200),
    ]);

    $result = Daytona::executeCommand('sandbox-123', 'php --version');

    expect($result->isSuccessful())->toBeTrue();
    expect($result->output)->toContain('PHP 8.3.0');
});

it('can work with files using facade', function () {
    Http::fake([
        '*/toolbox/*/toolbox/files/upload*' => Http::response([], 200),
        '*/toolbox/*/toolbox/files/download*' => Http::response('Hello from Facade!', 200),
    ]);

    Daytona::writeFile('sandbox-123', '/workspace/test.txt', 'Hello from Facade!');
    $content = Daytona::readFile('sandbox-123', '/workspace/test.txt');

    expect($content)->toBe('Hello from Facade!');
});

it('can get sandbox by id using facade', function () {
    Http::fake([
        '*/sandbox/*' => Http::response([
            'id' => 'sandbox-456',
            'organizationId' => 'org-123',
            'state' => 'started',
            'target' => 'us',
            'snapshot' => 'node-20',
            'createdAt' => now()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ], 200),
    ]);

    $sandbox = Daytona::getSandboxById('sandbox-456');

    expect($sandbox)->toBeInstanceOf(Sandbox::class);
    expect($sandbox->getId())->toBe('sandbox-456');
    expect($sandbox->getSnapshot())->toBe('node-20');
});
