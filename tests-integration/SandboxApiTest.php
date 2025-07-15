<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\CommandResponse;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use ElliottLawson\Daytona\DTOs\SandboxResponse;
use ElliottLawson\Daytona\Sandbox;

beforeEach(function () {
    $apiKey = env('DAYTONA_API_KEY');

    if (! $apiKey) {
        $this->markTestSkipped('DAYTONA_API_KEY environment variable is not set');
    }

    $this->client = new DaytonaClient(new Config(
        apiKey: $apiKey,
        apiUrl: env('DAYTONA_API_URL', 'https://app.daytona.io/api'),
        organizationId: env('DAYTONA_ORGANIZATION_ID'),
    ));
});

it('can create a sandbox', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters);

    $wdr = '/home/daytona/laravel';

    $sandbox->gitClone(
        url: 'https://github.com/elliottlawson/test-repo.git',
        path: '/home/daytona/laravel',
        branch: 'master',
        username: env('GITHUB_USERNAME'),
        password: env('GITHUB_TOKEN'),
    );
    $sandbox->exec("bash -c 'gh", cwd: $wdr);
    $sandbox->exec("bash -c 'git checkout -b test-branch'", cwd: $wdr);
    $sandbox->writeFile(
        path: $wdr.'/test2.txt',
        content: 'Hello from Daytona2!',
    );
    $sandbox->gitAdd(
        repoPath: $wdr,
        filePaths: ['test2.txt'],
    );
    $sandbox->exec(
        command: "bash -c 'git status'",
        cwd: $wdr,
    );
    $sandbox->gitCommit(
        repoPath: $wdr,
        message: 'Add new test file',
        authorName: 'Elliott Lawson',
        authorEmail: 'elliott@example.com',
    );
    $sandbox->exec(
        command: "bash -c 'git status'",
        cwd: $wdr,
    );
    $sandbox->gitPush(
        repoPath: $wdr,
        username: env('GITHUB_USERNAME'),
        password: env('GITHUB_TOKEN'),
    );
    $sandbox->exec(
        command: "bash -c 'git status'",
        cwd: $wdr,
    );

    $sandbox->delete();
})->skip('Manual test for Git operations');

it('creates a sandbox with minimal parameters and validates response structure', function () {
    $params = new SandboxCreateParameters;

    $sandbox = $this->client->createSandbox($params);

    expect($sandbox)->toBeInstanceOf(Sandbox::class);
    expect($sandbox->getId())->toBeString()->not->toBeEmpty();
    expect($sandbox->getOrganizationId())->toBeString();
    expect($sandbox->getState())->toBeIn(['started', 'starting', 'stopped', 'stopping']);
    expect($sandbox->getCreatedAt())->toBeString();
    expect($sandbox->getUpdatedAt())->toBeString();

    $sandbox->delete();
});

it('creates a sandbox with custom parameters', function () {
    $params = new SandboxCreateParameters(
        user: 'daytona',
        language: 'php',
        envVars: ['NODE_ENV' => 'development', 'DEBUG' => 'true'],
        labels: ['environment' => 'testing', 'project' => 'php-sdk'],
        public: false,
        autoStopInterval: 30,
        autoArchiveInterval: 10080,
        autoDeleteInterval: -1,
        snapshot: 'daytonaio/sandbox:0.4.3',
        target: 'us',
        class: 'medium',
    );

    $sandbox = $this->client->createSandbox($params);

    expect($sandbox)->toBeInstanceOf(Sandbox::class);
    expect($sandbox->getId())->toBeString()->not->toBeEmpty();

    // Validate that our custom parameters were applied (if API supports them)
    if ($sandbox->getData()?->class !== null) {
        expect($sandbox->getData()->class)->toBe('medium');
    }
    if ($sandbox->getData()?->target !== null) {
        expect($sandbox->getData()->target)->toBe('us');
    }

    $sandbox->delete();
});

it('validates all fields in sandbox response match expected types', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters);
    $response = $sandbox->getData();

    // Validate field types
    expect($sandbox->getId())->toBeString();

    if ($response->organizationId !== null) {
        expect($response->organizationId)->toBeString();
    }

    if ($response->target !== null) {
        expect($response->target)->toBeString();
    }

    if ($response->snapshot !== null) {
        expect($response->snapshot)->toBeString();
    }

    if ($response->user !== null) {
        expect($response->user)->toBeString();
    }

    if ($response->env !== null) {
        expect($response->env)->toBeArray();
    }

    if ($response->cpu !== null) {
        expect($response->cpu)->toBeInt();
    }

    if ($response->gpu !== null) {
        expect($response->gpu)->toBeInt();
    }

    if ($response->memory !== null) {
        expect($response->memory)->toBeInt();
    }

    if ($response->disk !== null) {
        expect($response->disk)->toBeInt();
    }

    if ($response->public !== null) {
        expect($response->public)->toBeBool();
    }

    if ($response->labels !== null) {
        expect($response->labels)->toBeArray();
    }

    if ($response->volumes !== null) {
        expect($response->volumes)->toBeArray();
    }

    if ($response->state !== null) {
        expect($response->state)->toBeString();
    }

    if ($response->desiredState !== null) {
        expect($response->desiredState)->toBeString();
    }

    if ($response->backupState !== null) {
        expect($response->backupState)->toBeString();
    }

    if ($response->autoStopInterval !== null) {
        expect($response->autoStopInterval)->toBeInt();
    }

    if ($response->autoArchiveInterval !== null) {
        expect($response->autoArchiveInterval)->toBeInt();
    }

    if ($response->autoDeleteInterval !== null) {
        expect($response->autoDeleteInterval)->toBeInt();
    }

    if ($response->class !== null) {
        expect($response->class)->toBeString();
    }

    if ($response->createdAt !== null) {
        expect($response->createdAt)->toBeString();
    }

    if ($response->updatedAt !== null) {
        expect($response->updatedAt)->toBeString();
    }

    if ($response->runnerDomain !== null) {
        expect($response->runnerDomain)->toBeString();
    }

    $sandbox->delete();
});

it('can retrieve sandbox details after creation', function () {
    $createdSandbox = $this->client->createSandbox(new SandboxCreateParameters);

    $getResponse = $this->client->getSandbox($createdSandbox->getId());

    expect($getResponse)->toBeInstanceOf(SandboxResponse::class);
    expect($getResponse->id)->toBe($createdSandbox->getId());
    expect($getResponse->organizationId)->toBe($createdSandbox->getOrganizationId());

    $createdSandbox->delete();
});

it('validates sandbox lifecycle operations', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        language: 'php',
        envVars: ['TEST_ENV' => 'lifecycle_test'],
    ));

    // Stop the sandbox if it's running
    if ($sandbox->getState() === 'started') {
        $sandbox->stop();

        // Wait a moment for state change
        sleep(2);

        $sandbox->refresh();
        expect($sandbox->getState())->toBeIn(['stopped', 'stopping']);
    }

    // Start the sandbox
    $sandbox->start();

    // Wait a moment for state change
    sleep(2);

    $sandbox->refresh();
    expect($sandbox->getState())->toBeIn(['started', 'starting']);

    $sandbox->delete();
});

it('handles long-running commands with custom timeout', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        language: 'php',
        envVars: ['TEST_ENV' => 'timeout_test'],
    ));

    // Test a command that sleeps for 35 seconds with a 45-second timeout (45000ms)
    // This would fail with the default 30-second HTTP timeout
    $response = $sandbox->exec(
        command: 'sleep 35 && echo "Command completed after 35 seconds"',
        timeout: 45000 // 45 seconds in milliseconds
    );

    expect($response)->toBeInstanceOf(CommandResponse::class);
    expect($response->isSuccessful())->toBeTrue();
    expect($response->output)->toContain('Command completed after 35 seconds');

    $sandbox->delete();
})->skip(env('SKIP_LONG_TESTS', true), 'Long-running test, set SKIP_LONG_TESTS=false to run');

it('respects command timeout and fails appropriately', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        language: 'php',
        envVars: ['TEST_ENV' => 'timeout_fail_test'],
    ));

    // Test a command that would take 10 seconds but with only 2-second timeout
    $response = $sandbox->exec(
        command: 'sleep 10 && echo "This should not appear"',
        timeout: 2000 // 2 seconds in milliseconds
    );

    // The command should fail due to timeout
    expect($response)->toBeInstanceOf(CommandResponse::class);
    expect($response->isSuccessful())->toBeFalse();
    expect($response->output)->not->toContain('This should not appear');

    $sandbox->delete();
});
