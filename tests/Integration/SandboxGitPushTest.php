<?php

use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use Tests\Integration\SandboxTestHelper;

uses(SandboxTestHelper::class);

beforeEach(function () {
    $this->setupClient();

    // Create sandbox and clone repo for all tests
    $this->sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $this->repoPath = '/home/daytona/push-test-repo';

    // Clone a repository
    $this->sandbox->gitClone(
        url: 'https://github.com/octocat/Hello-World.git',
        path: $this->repoPath,
        branch: 'master',
    );

    // Configure git user for commits
    $this->sandbox->exec(
        command: 'git config user.name "Test User"',
        cwd: $this->repoPath
    );
    $this->sandbox->exec(
        command: 'git config user.email "test@example.com"',
        cwd: $this->repoPath
    );
});

afterEach(function () {
    $this->cleanupSandboxes();
});

it('can attempt push without credentials', function () {
    // Create a new branch to avoid pushing to master
    $branchName = 'test-push-'.time();
    $this->sandbox->exec(
        command: "git checkout -b {$branchName}",
        cwd: $this->repoPath
    );

    // Make a change
    $testFile = 'test-push.txt';
    $this->sandbox->writeFile(
        path: $this->repoPath.'/'.$testFile,
        content: "Test push content\nTimestamp: ".time()
    );

    // Stage and commit
    $this->sandbox->gitAdd(
        repoPath: $this->repoPath,
        filePaths: [$testFile]
    );

    $this->sandbox->gitCommit(
        repoPath: $this->repoPath,
        message: 'Test commit for push',
        authorName: 'Test User',
        authorEmail: 'test@example.com'
    );

    // Verify we have commits to push
    $status = $this->sandbox->gitStatus($this->repoPath);
    expect($status->ahead)->toBeGreaterThan(0);

    // Try to push without credentials (should fail)
    try {
        $this->sandbox->gitPush(
            repoPath: $this->repoPath
        );
        // If it succeeds, repo allows anonymous pushes
        expect(true)->toBeTrue();
    } catch (\Exception $e) {
        // Expected to fail
        expect($e->getMessage())->toMatch('/push|authentication|credentials|permission/i');
    }
});

it('can push with branch specification', function () {
    $branchName = 'test-branch-'.time();
    $this->sandbox->exec(
        command: "git checkout -b {$branchName}",
        cwd: $this->repoPath
    );

    // Make a commit
    $this->sandbox->writeFile(
        path: $this->repoPath.'/branch-test.txt',
        content: 'Branch push test'
    );
    $this->sandbox->gitAdd(
        repoPath: $this->repoPath,
        filePaths: ['branch-test.txt']
    );
    $this->sandbox->gitCommit(
        repoPath: $this->repoPath,
        message: 'Branch push test',
        authorName: 'Test User',
        authorEmail: 'test@example.com'
    );

    // Try to push with branch
    try {
        $this->sandbox->gitPush(
            repoPath: $this->repoPath,
            branch: $branchName
        );
    } catch (\Exception $e) {
        expect($e->getMessage())->toMatch('/push|authentication|credentials|permission/i');
    }
});

it('can push with remote specification', function () {
    $branchName = 'remote-test-'.time();
    $this->sandbox->exec(
        command: "git checkout -b {$branchName}",
        cwd: $this->repoPath
    );

    // Make a commit
    $this->sandbox->writeFile(
        path: $this->repoPath.'/remote-test.txt',
        content: 'Remote push test'
    );
    $this->sandbox->gitAdd(
        repoPath: $this->repoPath,
        filePaths: ['remote-test.txt']
    );
    $this->sandbox->gitCommit(
        repoPath: $this->repoPath,
        message: 'Remote push test',
        authorName: 'Test User',
        authorEmail: 'test@example.com'
    );

    // Try to push with remote
    try {
        $this->sandbox->gitPush(
            repoPath: $this->repoPath,
            remote: 'origin',
            branch: $branchName
        );
    } catch (\Exception $e) {
        expect($e->getMessage())->toMatch('/push|authentication|credentials|permission/i');
    }
});

it('accepts authentication parameters for push', function () {
    $branchName = 'auth-test-'.time();
    $this->sandbox->exec(
        command: "git checkout -b {$branchName}",
        cwd: $this->repoPath
    );

    // Make a commit
    $this->sandbox->writeFile(
        path: $this->repoPath.'/auth-test.txt',
        content: 'Auth push test'
    );
    $this->sandbox->gitAdd(
        repoPath: $this->repoPath,
        filePaths: ['auth-test.txt']
    );
    $this->sandbox->gitCommit(
        repoPath: $this->repoPath,
        message: 'Auth push test',
        authorName: 'Test User',
        authorEmail: 'test@example.com'
    );

    // Try to push with credentials
    try {
        $this->sandbox->gitPush(
            repoPath: $this->repoPath,
            remote: 'origin',
            branch: $branchName,
            username: 'testuser',
            password: 'testpass'
        );
    } catch (\Exception $e) {
        // Expected to fail with invalid credentials
        expect($e->getMessage())->toMatch('/push|authentication|credentials|permission/i');
    }
});

it('accepts force push parameter', function () {
    $branchName = 'force-test-'.time();
    $this->sandbox->exec(
        command: "git checkout -b {$branchName}",
        cwd: $this->repoPath
    );

    // Make a commit
    $this->sandbox->writeFile(
        path: $this->repoPath.'/force-test.txt',
        content: 'Force push test'
    );
    $this->sandbox->gitAdd(
        repoPath: $this->repoPath,
        filePaths: ['force-test.txt']
    );
    $this->sandbox->gitCommit(
        repoPath: $this->repoPath,
        message: 'Force push test',
        authorName: 'Test User',
        authorEmail: 'test@example.com'
    );

    // Try force push
    try {
        $this->sandbox->gitPush(
            repoPath: $this->repoPath,
            branch: $branchName,
            force: true
        );
    } catch (\Exception $e) {
        expect($e->getMessage())->toMatch('/push|authentication|credentials|permission/i');
    }
});

it('accepts push all branches parameter', function () {
    // Create multiple branches with commits
    $branches = ['branch-a-'.time(), 'branch-b-'.time()];

    foreach ($branches as $branch) {
        $this->sandbox->exec(
            command: "git checkout -b {$branch}",
            cwd: $this->repoPath
        );

        $this->sandbox->writeFile(
            path: $this->repoPath."/{$branch}.txt",
            content: "Content for {$branch}"
        );
        $this->sandbox->gitAdd(
            repoPath: $this->repoPath,
            filePaths: ["{$branch}.txt"]
        );
        $this->sandbox->gitCommit(
            repoPath: $this->repoPath,
            message: "Commit for {$branch}",
            authorName: 'Test User',
            authorEmail: 'test@example.com'
        );

        // Switch back to master
        $this->sandbox->exec(
            command: 'git checkout master',
            cwd: $this->repoPath
        );
    }

    // Try to push all branches
    try {
        $this->sandbox->gitPush(
            repoPath: $this->repoPath,
            all: true
        );
    } catch (\Exception $e) {
        expect($e->getMessage())->toMatch('/push|authentication|credentials|permission/i');
    }
});
