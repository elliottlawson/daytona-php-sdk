<?php

use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use Tests\Integration\SandboxTestHelper;

uses(SandboxTestHelper::class);

beforeEach(function () {
    $this->setupClient();
});

afterEach(function () {
    $this->cleanupSandboxes();
});

it('can clone a public repository', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $repoPath = '/home/daytona/test-repo';

    // Clone a public repository
    $sandbox->gitClone(
        url: 'https://github.com/octocat/Hello-World.git',
        path: $repoPath,
        branch: 'master',
    );

    // Verify clone worked by checking directory exists
    $listing = $sandbox->listDirectory('/home/daytona');
    $allItems = array_map(fn ($file) => $file->name, $listing->files);
    expect($allItems)->toContain('test-repo');

    // Verify repository contents
    expect($sandbox->fileExists($repoPath.'/.git'))->toBeTrue();
    expect($sandbox->fileExists($repoPath.'/README'))->toBeTrue();
});

it('can clone a repository with specific branch', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $repoPath = '/home/daytona/branch-test-repo';

    // Clone with master branch explicitly
    $sandbox->gitClone(
        url: 'https://github.com/octocat/Hello-World.git',
        path: $repoPath,
        branch: 'master',
    );

    // Verify we're on the correct branch
    $currentBranch = $sandbox->exec(
        command: 'git branch --show-current',
        cwd: $repoPath
    );
    expect(trim($currentBranch->output))->toBe('master');
});

it('handles clone errors gracefully', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    // Try to clone a non-existent repository
    try {
        $sandbox->gitClone(
            url: 'https://github.com/this-does-not-exist-999999/repo.git',
            path: '/home/daytona/error-repo',
            branch: 'main',
        );
        fail('Clone should have failed');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('clone');
    }
});

it('can clone with authentication parameters', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    // Test that authentication parameters are accepted
    // This will fail but we're testing the API accepts the parameters
    try {
        $sandbox->gitClone(
            url: 'https://github.com/private/repo.git',
            branch: 'main',
            path: '/home/daytona/private-repo',
            username: 'testuser',
            password: 'testpass'
        );
    } catch (\Exception $e) {
        // Expected to fail - just testing parameter acceptance
        expect($e->getMessage())->toContain('clone');
    }
});
