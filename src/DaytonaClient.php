<?php

namespace ElliottLawson\Daytona;

use ElliottLawson\Daytona\DTOs\CommandResponse;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\DirectoryListingResponse;
use ElliottLawson\Daytona\DTOs\GitBranchesResponse;
use ElliottLawson\Daytona\DTOs\GitHistoryResponse;
use ElliottLawson\Daytona\DTOs\GitStatusResponse;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use ElliottLawson\Daytona\DTOs\SandboxResponse;
use ElliottLawson\Daytona\Exceptions\ApiException;
use ElliottLawson\Daytona\Exceptions\CommandExecutionException;
use ElliottLawson\Daytona\Exceptions\ConfigurationException;
use ElliottLawson\Daytona\Exceptions\FileSystemException;
use ElliottLawson\Daytona\Exceptions\GitException;
use ElliottLawson\Daytona\Exceptions\SandboxException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DaytonaClient
{
    public function __construct(
        private Config $config,
    ) {
        if (empty($this->config->apiKey)) {
            throw ConfigurationException::missingApiKey();
        }
    }

    private function client(?int $timeout = 30)
    {
        $client = Http::withToken($this->config->apiKey)
            ->baseUrl($this->config->apiUrl)
            ->timeout($timeout)
            ->acceptJson();

        if ($this->config->organizationId) {
            $client->withHeaders([
                'X-Daytona-Organization-ID' => $this->config->organizationId,
            ]);
        }

        return $client;
    }

    public function createSandbox(SandboxCreateParameters $params): Sandbox
    {
        try {
            Log::info('Creating Daytona sandbox', ['params' => $params->toArray()]);

            $response = $this->client()->post('sandbox', $params->toArray());

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'create sandbox');
            }

            $data = $response->json();

            if (! isset($data['id'])) {
                throw SandboxException::invalidResponse('missing sandbox ID');
            }

            Log::info('Daytona sandbox created', [
                'sandboxId' => $data['id'],
                'state' => $data['state'] ?? 'unknown',
                'response' => $data,
            ]);

            $sandboxResponse = SandboxResponse::fromArray($data);
            $sandbox = new Sandbox($sandboxResponse->id, $this, $sandboxResponse);

            return $sandbox;
        } catch (RequestException $e) {
            Log::error('Failed to create Daytona sandbox', [
                'error' => $e->getMessage(),
                'params' => $params->toArray(),
            ]);
            throw SandboxException::creationFailed($e->getMessage(), $e);
        }
    }

    public function deleteSandbox(string $sandboxId): void
    {
        try {
            Log::info('Deleting Daytona sandbox', ['sandboxId' => $sandboxId]);

            $response = $this->client()->delete("sandbox/{$sandboxId}");

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'delete sandbox');
            }

            Log::info('Daytona sandbox deleted', ['sandboxId' => $sandboxId]);
        } catch (RequestException $e) {
            Log::error('Failed to delete Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'error' => $e->getMessage(),
            ]);
            throw SandboxException::deletionFailed($sandboxId, $e->getMessage(), $e);
        }
    }

    public function getSandbox(string $sandboxId): SandboxResponse
    {
        try {
            Log::debug('Getting Daytona sandbox details', ['sandboxId' => $sandboxId]);

            $response = $this->client()->get("sandbox/{$sandboxId}");

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'get sandbox details');
            }

            $sandbox = $response->json();

            Log::debug('Sandbox details retrieved', [
                'sandboxId' => $sandboxId,
                'status' => $sandbox['status'] ?? 'unknown',
                'state' => $sandbox['state'] ?? 'unknown',
                'sandbox' => $sandbox,
            ]);

            return SandboxResponse::fromArray($sandbox);
        } catch (RequestException $e) {
            Log::error('Failed to get Daytona sandbox details', [
                'sandboxId' => $sandboxId,
                'error' => $e->getMessage(),
            ]);
            if ($e->response) {
                throw ApiException::fromResponse($e->response, 'get sandbox details');
            }
            throw ApiException::networkError('get sandbox details', $e);
        }
    }

    public function startSandbox(string $sandboxId): void
    {
        try {
            Log::info('Starting Daytona sandbox', ['sandboxId' => $sandboxId]);

            $response = $this->client()->post("sandbox/{$sandboxId}/start");

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'start sandbox');
            }

            Log::info('Daytona sandbox started', ['sandboxId' => $sandboxId]);
        } catch (RequestException $e) {
            Log::error('Failed to start Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'error' => $e->getMessage(),
            ]);
            throw SandboxException::startFailed($sandboxId, $e->getMessage(), $e);
        }
    }

    public function stopSandbox(string $sandboxId): void
    {
        try {
            Log::info('Stopping Daytona sandbox', ['sandboxId' => $sandboxId]);

            $response = $this->client()->post("sandbox/{$sandboxId}/stop");

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'stop sandbox');
            }

            Log::info('Daytona sandbox stopped', ['sandboxId' => $sandboxId]);
        } catch (RequestException $e) {
            Log::error('Failed to stop Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'error' => $e->getMessage(),
            ]);
            throw SandboxException::stopFailed($sandboxId, $e->getMessage(), $e);
        }
    }

    public function executeCommand(string $sandboxId, string $command, ?string $cwd = null, ?array $env = null, ?int $timeout = null): CommandResponse
    {
        try {
            $payload = [
                'command' => $command,
            ];

            if ($cwd !== null) {
                $payload['cwd'] = $cwd;
            }

            if ($env !== null) {
                $payload['env'] = $env;
            }

            if ($timeout !== null) {
                $payload['timeout'] = $timeout;
            }

            Log::debug('Executing command in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'command' => $command,
                'cwd' => $cwd,
                'env' => $env,
                'timeout' => $timeout,
            ]);

            // Calculate HTTP timeout based on command timeout + buffer
            $httpTimeout = $timeout ? (int)ceil($timeout / 1000) + 10 : 300; // Convert ms to seconds + buffer
            $response = $this->client($httpTimeout)->post("toolbox/{$sandboxId}/toolbox/process/execute", $payload);

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'execute command');
            }

            $result = $response->json();

            Log::debug('Command execution completed', [
                'sandboxId' => $sandboxId,
                'exitCode' => $result['exitCode'] ?? null,
                'result' => $result,  // Log the full result to see the structure
                'response_body' => $response->body(), // Log raw response
            ]);

            return CommandResponseParser::parse($result);
        } catch (RequestException $e) {
            Log::error('Failed to execute command in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'command' => $command,
                'cwd' => $cwd,
                'env' => $env,
                'timeout' => $timeout,
                'error' => $e->getMessage(),
            ]);
            throw CommandExecutionException::executionFailed($command, $e->getMessage(), $e);
        }
    }

    public function readFile(string $sandboxId, string $path): string
    {
        try {
            Log::debug('Reading file from Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
            ]);

            $response = $this->client()->get("toolbox/{$sandboxId}/toolbox/files/download", [
                'path' => $path,
            ]);

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'read file');
            }

            return $response->body();
        } catch (RequestException $e) {
            Log::error('Failed to read file from Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw FileSystemException::readFailed($path, $e->getMessage(), $e);
        }
    }

    public function writeFile(string $sandboxId, string $path, string $content): void
    {
        try {
            Log::debug('Writing file to Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'size' => strlen($content),
            ]);

            // Use multipart form data for file upload
            // It seems the API expects path as a query parameter
            $response = $this->client()
                ->asMultipart()
                ->post("toolbox/{$sandboxId}/toolbox/files/upload?path=".urlencode($path), [
                    [
                        'name' => 'file',
                        'contents' => $content,
                        'filename' => basename($path),
                    ],
                ]);

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'write file');
            }

            Log::debug('File written successfully', [
                'sandboxId' => $sandboxId,
                'path' => $path,
            ]);
        } catch (RequestException $e) {
            Log::error('Failed to write file to Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw FileSystemException::writeFailed($path, $e->getMessage(), $e);
        }
    }

    public function listDirectory(string $sandboxId, string $path): DirectoryListingResponse
    {
        try {
            Log::debug('Listing directory in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
            ]);

            $response = $this->client()->get("toolbox/{$sandboxId}/toolbox/files", [
                'path' => $path,
            ]);

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'list directory');
            }

            return DirectoryListingResponse::fromArray($response->json());
        } catch (RequestException $e) {
            Log::error('Failed to list directory in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw FileSystemException::listDirectoryFailed($path, $e->getMessage(), $e);
        }
    }

    public function deleteFile(string $sandboxId, string $path): void
    {
        try {
            Log::debug('Deleting file from Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
            ]);

            $response = $this->client()->delete("toolbox/{$sandboxId}/toolbox/files?path=".urlencode($path));

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'delete file');
            }

            Log::debug('File deleted successfully', [
                'sandboxId' => $sandboxId,
                'path' => $path,
            ]);
        } catch (RequestException $e) {
            Log::error('Failed to delete file from Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw FileSystemException::deleteFailed($path, $e->getMessage(), $e);
        }
    }

    public function fileExists(string $sandboxId, string $path): bool
    {
        try {
            Log::debug('Checking file existence in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
            ]);

            $response = $this->client()->get("toolbox/{$sandboxId}/toolbox/files/info", [
                'path' => $path,
            ]);

            return $response->successful();
        } catch (RequestException $e) {
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'not found')) {
                return false;
            }
            throw FileSystemException::checkExistenceFailed($path, $e->getMessage(), $e);
        }
    }

    /**
     * Clone a Git repository into the sandbox.
     */
    public function gitClone(string $sandboxId, string $url, ?string $branch = null, ?string $path = null, ?string $username = null, ?string $password = null): void
    {
        try {
            Log::info('Cloning repository in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'url' => $url,
                'branch' => $branch,
                'path' => $path,
            ]);

            $payload = [
                'url' => $url,
                //'path' => $path,
            ];

            if ($path) {
                $payload['path'] = $path;
            }

            if ($branch) {
                $payload['branch'] = $branch;
            }

            // Add authentication if provided
            if ($username && $password) {
                $payload['username'] = $username;
                $payload['password'] = $password;
            }

            $response = $this->client()->post("toolbox/{$sandboxId}/toolbox/git/clone", $payload);
            ray($response->json())->blue();

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'clone repository');
            }

            Log::info('Repository cloned successfully', [
                'sandboxId' => $sandboxId,
                'url' => $url,
            ]);
        } catch (RequestException $e) {
            Log::error('Failed to clone repository in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw GitException::cloneFailed($url, $e->getMessage(), $e);
        }
    }

    /**
     * List Git branches in the repository.
     */
    public function gitListBranches(string $sandboxId, string $repoPath = '/workspace'): GitBranchesResponse
    {
        try {
            Log::debug('Listing Git branches in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
            ]);

            $response = $this->client()->get("toolbox/{$sandboxId}/toolbox/git/branches", [
                'path' => $repoPath,
            ]);

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'list branches');
            }

            return GitBranchesResponse::fromArray($response->json());
        } catch (RequestException $e) {
            Log::error('Failed to list Git branches in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
                'error' => $e->getMessage(),
            ]);
            throw GitException::branchListFailed($repoPath, $e->getMessage(), $e);
        }
    }

    /**
     * Add files to Git staging area.
     */
    public function gitAdd(string $sandboxId, string $repoPath, array $filePaths): void
    {
        try {
            Log::debug('Adding files to Git staging in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
                'filePaths' => $filePaths,
            ]);

            $response = $this->client()->post("toolbox/{$sandboxId}/toolbox/git/add", [
                'Path' => $repoPath,
                'Files' => $filePaths,
            ]);

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'add files to Git');
            }
        } catch (RequestException $e) {
            Log::error('Failed to add files to Git in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
                'error' => $e->getMessage(),
            ]);
            throw GitException::addFailed($filePaths, $e->getMessage(), $e);
        }
    }

    /**
     * Commit changes in Git.
     */
    public function gitCommit(string $sandboxId, string $repoPath, string $message, string $authorName, string $authorEmail): void
    {
        try {
            Log::info('Committing changes in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
                'message' => $message,
            ]);

            $response = $this->client()->post("toolbox/{$sandboxId}/toolbox/git/commit", [
                'Path' => $repoPath,
                'Message' => $message,
                'Author' => $authorName,
                'Email' => $authorEmail,
            ]);

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'commit changes');
            }
        } catch (RequestException $e) {
            Log::error('Failed to commit changes in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
                'error' => $e->getMessage(),
            ]);
            throw GitException::commitFailed($message, $e->getMessage(), $e);
        }
    }

    /**
     * Push changes to remote repository.
     */
    public function gitPush(string $sandboxId, string $repoPath, ?string $username = null, ?string $password = null): void
    {
        try {
            Log::info('Pushing changes to remote in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
            ]);

            $payload = [
                'Path' => $repoPath,
            ];

            if ($username && $password) {
                $payload['Username'] = $username;
                $payload['Password'] = $password;
            }

            $response = $this->client()->post("toolbox/{$sandboxId}/toolbox/git/push", $payload);

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'push changes');
            }

            Log::info('Changes pushed successfully', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
            ]);
        } catch (RequestException $e) {
            Log::error('Failed to push changes in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
                'error' => $e->getMessage(),
            ]);
            throw GitException::pushFailed($e->getMessage(), $e);
        }
    }

    /**
     * Get Git status.
     */
    public function gitStatus(string $sandboxId, string $repoPath = '/workspace'): GitStatusResponse
    {
        try {
            Log::debug('Getting Git status in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
            ]);

            $response = $this->client()->get("toolbox/{$sandboxId}/toolbox/git/status", [
                'path' => $repoPath,
            ]);

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'get Git status');
            }

            return GitStatusResponse::fromArray($response->json());
        } catch (RequestException $e) {
            Log::error('Failed to get Git status in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
                'error' => $e->getMessage(),
            ]);
            throw GitException::statusFailed($repoPath, $e->getMessage(), $e);
        }
    }

    /**
     * Get Git commit history.
     */
    public function gitHistory(string $sandboxId, string $repoPath = '/workspace'): GitHistoryResponse
    {
        try {
            Log::debug('Getting Git history in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
            ]);

            $response = $this->client()->get("toolbox/{$sandboxId}/toolbox/git/history", [
                'path' => $repoPath,
            ]);

            if (! $response->successful()) {
                throw ApiException::fromResponse($response, 'get Git history');
            }

            return GitHistoryResponse::fromArray($response->json());
        } catch (RequestException $e) {
            Log::error('Failed to get Git history in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'repoPath' => $repoPath,
                'error' => $e->getMessage(),
            ]);
            throw GitException::historyFailed($repoPath, $e->getMessage(), $e);
        }
    }

    /**
     * Get a Sandbox instance by ID.
     * This fetches the latest data and returns a Sandbox object.
     */
    public function getSandboxById(string $sandboxId): Sandbox
    {
        $response = $this->getSandbox($sandboxId);

        return new Sandbox($sandboxId, $this, $response);
    }

    /**
     * Create a Sandbox instance from a SandboxResponse.
     * This is useful when you need the behavioral methods of Sandbox.
     */
    public function sandboxFromResponse(SandboxResponse $response): Sandbox
    {
        return new Sandbox($response->id, $this, $response);
    }
}
