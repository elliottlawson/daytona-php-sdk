<?php

use ElliottLawson\Daytona\DTOs\GitStatusResponse;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use Tests\Integration\SandboxTestHelper;

/**
 * Git Status Integration Tests
 */
uses(SandboxTestHelper::class);

beforeEach(function () {
    $this->setupClient();

    // Create sandbox and clone repo for all tests
    $this->sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $this->repoPath = '/home/daytona/status-test-repo';

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

it('can check git status of a clean repository', function () {
    $status = $this->sandbox->gitStatus($this->repoPath);

    expect($status)->toBeInstanceOf(GitStatusResponse::class);
    expect($status->branch)->toBe('master');
    expect($status->ahead)->toBe(0);
    expect($status->behind)->toBe(0);
    expect($status->staged)->toBeArray()->toBeEmpty();
    expect($status->unstaged)->toBeArray()->toBeEmpty();
    expect($status->untracked)->toBeArray()->toBeEmpty();
    expect($status->isClean())->toBeTrue();
});

it('detects untracked files', function () {
    // Create a new file
    $testFile = 'test-untracked.txt';
    $this->sandbox->writeFile(
        path: $this->repoPath.'/'.$testFile,
        content: 'This is an untracked file'
    );

    $status = $this->sandbox->gitStatus($this->repoPath);

    expect($status->untracked)->toContain($testFile);
    expect($status->staged)->toBeEmpty();
    expect($status->unstaged)->toBeEmpty();
    expect($status->isClean())->toBeFalse();
});

it('detects staged files', function () {
    // Create and stage a file
    $testFile = 'test-staged.txt';
    $this->sandbox->writeFile(
        path: $this->repoPath.'/'.$testFile,
        content: 'This file will be staged'
    );

    $this->sandbox->gitAdd(
        repoPath: $this->repoPath,
        filePaths: [$testFile]
    );

    $status = $this->sandbox->gitStatus($this->repoPath);

    expect($status->staged)->toContain($testFile);
    expect($status->untracked)->not->toContain($testFile);
    expect($status->unstaged)->toBeEmpty();
    expect($status->isClean())->toBeFalse();
});

it('detects modified files', function () {
    // Modify an existing file
    $readmePath = $this->repoPath.'/README';
    if ($this->sandbox->fileExists($readmePath)) {
        $originalContent = $this->sandbox->readFile($readmePath);
        $this->sandbox->writeFile(
            path: $readmePath,
            content: $originalContent."\n\nModified by test"
        );

        $status = $this->sandbox->gitStatus($this->repoPath);

        expect($status->unstaged)->toContain('README');
        expect($status->staged)->toBeEmpty();
        expect($status->untracked)->toBeEmpty();
        expect($status->isClean())->toBeFalse();
    } else {
        $this->markTestSkipped('README file not found in repository');
    }
});

it('tracks commit ahead count after local commits', function () {
    // Create and commit a new file
    $testFile = 'test-commit.txt';
    $this->sandbox->writeFile(
        path: $this->repoPath.'/'.$testFile,
        content: 'Test commit content'
    );

    $this->sandbox->gitAdd(
        repoPath: $this->repoPath,
        filePaths: [$testFile]
    );

    $this->sandbox->gitCommit(
        repoPath: $this->repoPath,
        message: 'Test commit',
        authorName: 'Test User',
        authorEmail: 'test@example.com'
    );

    $status = $this->sandbox->gitStatus($this->repoPath);

    expect($status->ahead)->toBe(1);
    expect($status->behind)->toBe(0);
    expect($status->isClean())->toBeTrue();
});

it('handles multiple file states simultaneously', function () {
    // Create multiple files in different states
    $untrackedFile = 'untracked.txt';
    $stagedFile = 'staged.txt';
    $modifiedFile = 'README';

    // Create untracked file
    $this->sandbox->writeFile(
        path: $this->repoPath.'/'.$untrackedFile,
        content: 'Untracked content'
    );

    // Create and stage a file
    $this->sandbox->writeFile(
        path: $this->repoPath.'/'.$stagedFile,
        content: 'Staged content'
    );
    $this->sandbox->gitAdd(
        repoPath: $this->repoPath,
        filePaths: [$stagedFile]
    );

    // Modify existing file
    if ($this->sandbox->fileExists($this->repoPath.'/'.$modifiedFile)) {
        $content = $this->sandbox->readFile($this->repoPath.'/'.$modifiedFile);
        $this->sandbox->writeFile(
            path: $this->repoPath.'/'.$modifiedFile,
            content: $content."\nModified"
        );
    }

    $status = $this->sandbox->gitStatus($this->repoPath);

    expect($status->untracked)->toContain($untrackedFile);
    expect($status->staged)->toContain($stagedFile);
    expect($status->unstaged)->toContain($modifiedFile);
    expect($status->isClean())->toBeFalse();
});
