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

it('can initialize a new git repository', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $repoPath = '/home/daytona/new-repo';

    // Create directory
    $sandbox->exec("mkdir -p {$repoPath}");

    // Initialize repository
    $initResult = $sandbox->exec(
        command: 'git init',
        cwd: $repoPath
    );
    expect($initResult->isSuccessful())->toBeTrue();
    expect($initResult->output)->toContain('Initialized empty Git repository');

    // Verify .git directory exists
    expect($sandbox->fileExists($repoPath.'/.git'))->toBeTrue();
});

it('can configure git settings', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $repoPath = '/home/daytona/config-repo';

    // Initialize repo
    $sandbox->exec("mkdir -p {$repoPath}");
    $sandbox->exec('git init', cwd: $repoPath);

    // Configure user
    $nameResult = $sandbox->exec(
        command: 'git config user.name "Integration Test"',
        cwd: $repoPath
    );
    expect($nameResult->isSuccessful())->toBeTrue();

    $emailResult = $sandbox->exec(
        command: 'git config user.email "test@integration.com"',
        cwd: $repoPath
    );
    expect($emailResult->isSuccessful())->toBeTrue();

    // Verify configuration
    $getName = $sandbox->exec(
        command: 'git config user.name',
        cwd: $repoPath
    );
    expect(trim($getName->output))->toBe('Integration Test');

    $getEmail = $sandbox->exec(
        command: 'git config user.email',
        cwd: $repoPath
    );
    expect(trim($getEmail->output))->toBe('test@integration.com');
});

it('can use git log with various formats', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $repoPath = '/home/daytona/log-repo';

    // Clone a repo with history
    $sandbox->gitClone(
        url: 'https://github.com/octocat/Hello-World.git',
        path: $repoPath,
        branch: 'master',
    );

    // Test oneline format
    $onelineLog = $sandbox->exec(
        command: 'git log --oneline -n 5',
        cwd: $repoPath
    );
    expect($onelineLog->isSuccessful())->toBeTrue();
    expect($onelineLog->output)->toMatch('/^[a-f0-9]{7} .+/m');

    // Test pretty format
    $prettyLog = $sandbox->exec(
        command: 'git log --pretty=format:"%h - %an, %ar : %s" -n 3',
        cwd: $repoPath
    );
    expect($prettyLog->isSuccessful())->toBeTrue();

    // Test graph format
    $graphLog = $sandbox->exec(
        command: 'git log --graph --oneline -n 5',
        cwd: $repoPath
    );
    expect($graphLog->isSuccessful())->toBeTrue();
});

it('can check git diff', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $repoPath = '/home/daytona/diff-repo';

    // Clone repo
    $sandbox->gitClone(
        url: 'https://github.com/octocat/Hello-World.git',
        path: $repoPath,
        branch: 'master',
    );

    // Modify a file
    $readmePath = $repoPath.'/README';
    $content = $sandbox->readFile($readmePath);
    $sandbox->writeFile(
        path: $readmePath,
        content: $content."\n\n## Added Section\nThis is a new section."
    );

    // Check diff
    $diffResult = $sandbox->exec(
        command: 'git diff',
        cwd: $repoPath
    );
    expect($diffResult->isSuccessful())->toBeTrue();
    expect($diffResult->output)->toContain('+## Added Section');
    expect($diffResult->output)->toContain('+This is a new section.');

    // Check diff with specific file
    $fileDiff = $sandbox->exec(
        command: 'git diff README',
        cwd: $repoPath
    );
    expect($fileDiff->isSuccessful())->toBeTrue();
    expect($fileDiff->output)->toContain('diff --git a/README b/README');
});

it('can work with git stash', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $repoPath = '/home/daytona/stash-repo';

    // Clone repo
    $sandbox->gitClone(
        url: 'https://github.com/octocat/Hello-World.git',
        path: $repoPath,
        branch: 'master',
    );

    // Configure git user
    $sandbox->exec('git config user.name "Test User"', cwd: $repoPath);
    $sandbox->exec('git config user.email "test@example.com"', cwd: $repoPath);

    // Make changes
    $sandbox->writeFile(
        path: $repoPath.'/stash-test.txt',
        content: 'This will be stashed'
    );
    $sandbox->exec('git add stash-test.txt', cwd: $repoPath);

    // Stash changes
    $stashResult = $sandbox->exec(
        command: 'git stash push -m "Test stash"',
        cwd: $repoPath
    );
    expect($stashResult->isSuccessful())->toBeTrue();

    // Verify file is gone
    expect($sandbox->fileExists($repoPath.'/stash-test.txt'))->toBeFalse();

    // List stashes
    $stashList = $sandbox->exec(
        command: 'git stash list',
        cwd: $repoPath
    );
    expect($stashList->output)->toContain('Test stash');

    // Apply stash
    $applyResult = $sandbox->exec(
        command: 'git stash pop',
        cwd: $repoPath
    );
    expect($applyResult->isSuccessful())->toBeTrue();

    // Verify file is back
    expect($sandbox->fileExists($repoPath.'/stash-test.txt'))->toBeTrue();
});

it('can work with git tags', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $repoPath = '/home/daytona/tag-repo';

    // Initialize new repo
    $sandbox->exec("mkdir -p {$repoPath}");
    $sandbox->exec('git init', cwd: $repoPath);
    $sandbox->exec('git config user.name "Test User"', cwd: $repoPath);
    $sandbox->exec('git config user.email "test@example.com"', cwd: $repoPath);

    // Create initial commit
    $sandbox->writeFile($repoPath.'/README.md', '# Tag Test Repository');
    $sandbox->exec('git add README.md', cwd: $repoPath);
    $sandbox->exec('git commit -m "Initial commit"', cwd: $repoPath);

    // Create lightweight tag
    $tagResult = $sandbox->exec(
        command: 'git tag v1.0.0',
        cwd: $repoPath
    );
    expect($tagResult->isSuccessful())->toBeTrue();

    // Create annotated tag
    $annotatedResult = $sandbox->exec(
        command: 'git tag -a v1.1.0 -m "Version 1.1.0 release"',
        cwd: $repoPath
    );
    expect($annotatedResult->isSuccessful())->toBeTrue();

    // List tags
    $listResult = $sandbox->exec(
        command: 'git tag -l',
        cwd: $repoPath
    );
    expect($listResult->output)->toContain('v1.0.0');
    expect($listResult->output)->toContain('v1.1.0');

    // Show tag details
    $showResult = $sandbox->exec(
        command: 'git show v1.1.0',
        cwd: $repoPath
    );
    expect($showResult->output)->toContain('Version 1.1.0 release');
});

it('can use git remote commands', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
    $repoPath = '/home/daytona/remote-repo';

    // Clone repo
    $sandbox->gitClone(
        url: 'https://github.com/octocat/Hello-World.git',
        path: $repoPath,
        branch: 'master',
    );

    // List remotes
    $listResult = $sandbox->exec(
        command: 'git remote -v',
        cwd: $repoPath
    );
    expect($listResult->isSuccessful())->toBeTrue();
    expect($listResult->output)->toContain('origin');
    expect($listResult->output)->toContain('github.com/octocat/Hello-World.git');

    // Get remote URL
    $urlResult = $sandbox->exec(
        command: 'git remote get-url origin',
        cwd: $repoPath
    );
    expect($urlResult->isSuccessful())->toBeTrue();
    expect(trim($urlResult->output))->toContain('github.com/octocat/Hello-World.git');

    // Add new remote
    $addResult = $sandbox->exec(
        command: 'git remote add upstream https://github.com/upstream/repo.git',
        cwd: $repoPath
    );
    expect($addResult->isSuccessful())->toBeTrue();

    // Verify new remote
    $verifyResult = $sandbox->exec(
        command: 'git remote -v',
        cwd: $repoPath
    );
    expect($verifyResult->output)->toContain('upstream');

    // Remove remote
    $removeResult = $sandbox->exec(
        command: 'git remote remove upstream',
        cwd: $repoPath
    );
    expect($removeResult->isSuccessful())->toBeTrue();
});
