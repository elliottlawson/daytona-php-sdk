<?php

use ElliottLawson\Daytona\DTOs\GitHistoryResponse;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use Tests\Integration\SandboxTestHelper;

uses(SandboxTestHelper::class);

beforeEach(function () {
    $this->setupClient();

    // Create sandbox and clone repo for all tests
    $this->sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $this->repoPath = '/home/daytona/commit-test-repo';

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

it('can view commit history', function () {
    $history = $this->client->gitHistory($this->sandbox->getId(), $this->repoPath);

    expect($history)->toBeInstanceOf(GitHistoryResponse::class);
    expect($history->commits)->toBeArray();
    expect(count($history->commits))->toBeGreaterThan(0);

    // Check first commit has expected properties
    $firstCommit = $history->commits[0];
    expect($firstCommit->hash)->toBeString();
    expect($firstCommit->message)->toBeString();
    expect($firstCommit->author)->toBeString();
    expect($firstCommit->date)->toBeString();
});

it('can make a simple commit', function () {
    // Create a new file
    $testFile = 'test-commit.txt';
    $content = "Test content\nCreated at: ".date('Y-m-d H:i:s');
    $this->sandbox->writeFile(
        path: $this->repoPath.'/'.$testFile,
        content: $content
    );

    // Stage the file
    $this->sandbox->gitAdd(
        repoPath: $this->repoPath,
        filePaths: [$testFile]
    );

    // Make the commit
    $commitMessage = 'Test commit from PHP SDK';
    $this->sandbox->gitCommit(
        repoPath: $this->repoPath,
        message: $commitMessage,
        authorName: 'Test User',
        authorEmail: 'test@example.com'
    );

    // Verify commit in history
    $history = $this->client->gitHistory($this->sandbox->getId(), $this->repoPath);
    expect($history->commits[0]->message)->toContain($commitMessage);
    expect($history->commits[0]->author)->toContain('Test User');
});

it('can stage and commit multiple files', function () {
    // Create multiple files
    $files = [
        'file1.txt' => 'Content of file 1',
        'file2.txt' => 'Content of file 2',
        'file3.txt' => 'Content of file 3',
    ];

    foreach ($files as $filename => $content) {
        $this->sandbox->writeFile(
            path: $this->repoPath.'/'.$filename,
            content: $content
        );
    }

    // Stage all files at once
    $this->sandbox->gitAdd(
        repoPath: $this->repoPath,
        filePaths: array_keys($files)
    );

    // Verify all files are staged
    $status = $this->sandbox->gitStatus($this->repoPath);
    foreach (array_keys($files) as $filename) {
        expect($status->staged)->toContain($filename);
    }

    // Commit all files
    $this->sandbox->gitCommit(
        repoPath: $this->repoPath,
        message: 'Added multiple files',
        authorName: 'Test User',
        authorEmail: 'test@example.com'
    );

    // Verify commit was made
    $statusAfter = $this->sandbox->gitStatus($this->repoPath);
    expect($statusAfter->staged)->toBeEmpty();
    expect($statusAfter->isClean())->toBeTrue();
});

it('can make multiple commits', function () {
    $commits = [
        ['file' => 'first.txt', 'message' => 'First commit'],
        ['file' => 'second.txt', 'message' => 'Second commit'],
        ['file' => 'third.txt', 'message' => 'Third commit'],
    ];

    foreach ($commits as $commit) {
        // Create file
        $this->sandbox->writeFile(
            path: $this->repoPath.'/'.$commit['file'],
            content: 'Content for '.$commit['file']
        );

        // Stage file
        $this->sandbox->gitAdd(
            repoPath: $this->repoPath,
            filePaths: [$commit['file']]
        );

        // Commit
        $this->sandbox->gitCommit(
            repoPath: $this->repoPath,
            message: $commit['message'],
            authorName: 'Test User',
            authorEmail: 'test@example.com'
        );
    }

    // Verify all commits in history
    $history = $this->client->gitHistory($this->sandbox->getId(), $this->repoPath);

    // Check commits appear in reverse order (newest first)
    expect($history->commits[0]->message)->toContain('Third commit');
    expect($history->commits[1]->message)->toContain('Second commit');
    expect($history->commits[2]->message)->toContain('First commit');
});

it('can stage specific files selectively', function () {
    // Create multiple files
    $this->sandbox->writeFile(
        path: $this->repoPath.'/include1.txt',
        content: 'This will be staged'
    );
    $this->sandbox->writeFile(
        path: $this->repoPath.'/include2.txt',
        content: 'This will also be staged'
    );
    $this->sandbox->writeFile(
        path: $this->repoPath.'/exclude.txt',
        content: 'This will NOT be staged'
    );

    // Stage only some files
    $this->sandbox->gitAdd(
        repoPath: $this->repoPath,
        filePaths: ['include1.txt', 'include2.txt']
    );

    // Check status
    $status = $this->sandbox->gitStatus($this->repoPath);
    expect($status->staged)->toContain('include1.txt');
    expect($status->staged)->toContain('include2.txt');
    expect($status->untracked)->toContain('exclude.txt');

    // Commit staged files
    $this->sandbox->gitCommit(
        repoPath: $this->repoPath,
        message: 'Selective commit',
        authorName: 'Test User',
        authorEmail: 'test@example.com'
    );

    // Verify excluded file is still untracked
    $statusAfter = $this->sandbox->gitStatus($this->repoPath);
    expect($statusAfter->untracked)->toContain('exclude.txt');
});
