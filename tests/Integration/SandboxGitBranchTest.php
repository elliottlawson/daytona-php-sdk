<?php

use ElliottLawson\Daytona\DTOs\GitBranchesResponse;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use Tests\Integration\SandboxTestHelper;

uses(SandboxTestHelper::class);

beforeEach(function () {
    $this->setupClient();
    
    // Create sandbox and clone repo for all tests
    $this->sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $this->repoPath = '/home/daytona/branch-test-repo';
    
    // Clone a repository
    $this->sandbox->gitClone(
        url: 'https://github.com/octocat/Hello-World.git',
        path: $this->repoPath,
        branch: 'master',
    );
});

afterEach(function () {
    $this->cleanupSandboxes();
});

it('can list branches in a repository', function () {
    $branches = $this->client->gitListBranches($this->sandbox->getId(), $this->repoPath);
    
    expect($branches)->toBeInstanceOf(GitBranchesResponse::class);
    expect($branches->branches)->toBeArray();
    expect($branches->branches)->toContain('master');
    // Note: currentBranch property is not provided by Daytona API
    expect($branches->currentBranch)->toBeNull();
});

it('can create a new branch', function () {
    $branchName = 'test-branch-' . time();
    
    // Create new branch using exec
    $result = $this->sandbox->exec(
        command: "git checkout -b {$branchName}",
        cwd: $this->repoPath
    );
    expect($result->isSuccessful())->toBeTrue();
    
    // Verify branch was created
    $currentBranch = $this->sandbox->exec(
        command: 'git branch --show-current',
        cwd: $this->repoPath
    );
    expect(trim($currentBranch->output))->toBe($branchName);
    
    // Verify branch appears in list
    $branches = $this->client->gitListBranches($this->sandbox->getId(), $this->repoPath);
    expect($branches->branches)->toContain($branchName);
    expect($branches->branches)->toContain('master');
});

it('can switch between branches', function () {
    // Create a new branch
    $newBranch = 'feature-test-' . time();
    $this->sandbox->exec(
        command: "git checkout -b {$newBranch}",
        cwd: $this->repoPath
    );
    
    // Verify we're on the new branch
    $current = $this->sandbox->exec(
        command: 'git branch --show-current',
        cwd: $this->repoPath
    );
    expect(trim($current->output))->toBe($newBranch);
    
    // Switch back to master
    $this->sandbox->exec(
        command: 'git checkout master',
        cwd: $this->repoPath
    );
    
    // Verify we're back on master
    $current = $this->sandbox->exec(
        command: 'git branch --show-current',
        cwd: $this->repoPath
    );
    expect(trim($current->output))->toBe('master');
});

it('can create multiple branches', function () {
    $branches = ['feature-a', 'feature-b', 'feature-c'];
    
    foreach ($branches as $branch) {
        $this->sandbox->exec(
            command: "git checkout -b {$branch}",
            cwd: $this->repoPath
        );
        
        // Switch back to master for next branch
        $this->sandbox->exec(
            command: 'git checkout master',
            cwd: $this->repoPath
        );
    }
    
    // List all branches
    $branchList = $this->client->gitListBranches($this->sandbox->getId(), $this->repoPath);
    
    foreach ($branches as $branch) {
        expect($branchList->branches)->toContain($branch);
    }
    expect($branchList->branches)->toContain('master');
});

it('can delete branches', function () {
    // Create a branch
    $branchToDelete = 'delete-me-' . time();
    $this->sandbox->exec(
        command: "git checkout -b {$branchToDelete}",
        cwd: $this->repoPath
    );
    
    // Switch back to master before deleting
    $this->sandbox->exec(
        command: 'git checkout master',
        cwd: $this->repoPath
    );
    
    // Delete the branch
    $deleteResult = $this->sandbox->exec(
        command: "git branch -d {$branchToDelete}",
        cwd: $this->repoPath
    );
    expect($deleteResult->isSuccessful())->toBeTrue();
    
    // Verify branch is gone
    $branches = $this->client->gitListBranches($this->sandbox->getId(), $this->repoPath);
    expect($branches->branches)->not->toContain($branchToDelete);
});