<?php

use ElliottLawson\Daytona\Exceptions\ApiException;
use ElliottLawson\Daytona\Exceptions\CommandExecutionException;
use ElliottLawson\Daytona\Exceptions\ConfigurationException;
use ElliottLawson\Daytona\Exceptions\FileSystemException;
use ElliottLawson\Daytona\Exceptions\GitException;
use ElliottLawson\Daytona\Exceptions\SandboxException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

it('creates ConfigurationException with proper messages', function () {
    $exception = ConfigurationException::missingApiKey();
    expect($exception->getMessage())->toContain('Daytona API token is not configured');
    
    $exception = ConfigurationException::missingOrganizationId();
    expect($exception->getMessage())->toContain('Daytona organization ID is not configured');
    
    $exception = ConfigurationException::invalidApiUrl('not-a-url');
    expect($exception->getMessage())->toContain('Invalid Daytona API URL: not-a-url');
});

it('creates SandboxException with proper messages', function () {
    $exception = SandboxException::creationFailed('Invalid parameters');
    expect($exception->getMessage())->toBe('Failed to create sandbox: Invalid parameters');
    
    $exception = SandboxException::notFound('sandbox-123');
    expect($exception->getMessage())->toBe('Sandbox not found: sandbox-123');
    
    $exception = SandboxException::failedToStart('sandbox-123', 'error');
    expect($exception->getMessage())->toBe('Sandbox sandbox-123 failed to start. Current state: error');
});

it('creates FileSystemException with proper messages', function () {
    $exception = FileSystemException::readFailed('/path/to/file.txt', 'Permission denied');
    expect($exception->getMessage())->toBe("Failed to read file '/path/to/file.txt': Permission denied");
    
    $exception = FileSystemException::fileNotFound('/missing/file.txt');
    expect($exception->getMessage())->toBe('File not found: /missing/file.txt');
    
    $exception = FileSystemException::accessDenied('/protected/file.txt');
    expect($exception->getMessage())->toBe('Access denied to file: /protected/file.txt');
});

it('creates CommandExecutionException with proper messages', function () {
    $exception = CommandExecutionException::executionFailed('npm install', 'Command not found');
    expect($exception->getMessage())->toBe("Failed to execute command 'npm install': Command not found");
    
    $exception = CommandExecutionException::nonZeroExitCode('npm test', 1, 'Test failed');
    expect($exception->getMessage())->toContain("Command 'npm test' failed with exit code 1");
    expect($exception->getMessage())->toContain('Error: Test failed');
    
    $exception = CommandExecutionException::timeout('long-running-command', 30);
    expect($exception->getMessage())->toBe("Command 'long-running-command' timed out after 30 seconds");
});

it('creates GitException with proper messages', function () {
    $exception = GitException::cloneFailed('https://github.com/repo.git', 'Repository not found');
    expect($exception->getMessage())->toBe("Failed to clone repository 'https://github.com/repo.git': Repository not found");
    
    $exception = GitException::commitFailed('Initial commit', 'Nothing to commit');
    expect($exception->getMessage())->toBe("Failed to commit changes 'Initial commit': Nothing to commit");
    
    $exception = GitException::authenticationFailed('https://github.com/private/repo.git');
    expect($exception->getMessage())->toBe('Git authentication failed for repository: https://github.com/private/repo.git');
});

it('creates ApiException from response with proper status codes', function () {
    // Test each status code in isolation
    
    // 401 response
    Http::fake(['*' => Http::response(['error' => 'Unauthorized'], 401)]);
    $response = Http::get('https://api.daytona.io/test');
    
    $exception = ApiException::fromResponse($response, 'test operation');
    expect($exception->getMessage())->toBe('Authentication failed for test operation. Please check your API key.');
    expect($exception->getStatusCode())->toBe(401);
    expect($exception->getResponse())->toBe($response);
});

it('creates ApiException for 403 response', function () {
    Http::fake(['*' => Http::response(['error' => 'Forbidden'], 403)]);
    $response = Http::get('https://api.daytona.io/test');
    
    $exception = ApiException::fromResponse($response, 'test operation');
    expect($exception->getMessage())->toBe('Access denied for test operation. Please check your permissions.');
    expect($exception->getStatusCode())->toBe(403);
});

it('creates ApiException for 429 response', function () {
    Http::fake(['*' => Http::response(['error' => 'Too many requests'], 429)]);
    $response = Http::get('https://api.daytona.io/test');
    
    $exception = ApiException::fromResponse($response, 'test operation');
    expect($exception->getMessage())->toBe('Rate limit exceeded for test operation. Please try again later.');
    expect($exception->getStatusCode())->toBe(429);
});

it('creates ApiException for 500 response', function () {
    Http::fake(['*' => Http::response(['error' => 'Server error'], 500)]);
    $response = Http::get('https://api.daytona.io/test');
    
    $exception = ApiException::fromResponse($response, 'test operation');
    expect($exception->getMessage())->toBe('Server error during test operation. Please try again later.');
    expect($exception->getStatusCode())->toBe(500);
});

it('preserves previous exceptions', function () {
    $previous = new \Exception('Original error');
    
    $exception = SandboxException::creationFailed('New error', $previous);
    expect($exception->getPrevious())->toBe($previous);
    
    $exception = FileSystemException::readFailed('/file.txt', 'Read error', $previous);
    expect($exception->getPrevious())->toBe($previous);
    
    $exception = CommandExecutionException::executionFailed('command', 'Error', $previous);
    expect($exception->getPrevious())->toBe($previous);
});