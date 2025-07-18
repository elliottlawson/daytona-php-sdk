<?php

namespace ElliottLawson\Daytona;

use ElliottLawson\Daytona\DTOs\CommandResponse;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\DirectoryListingResponse;
use ElliottLawson\Daytona\DTOs\FileInfo;
use ElliottLawson\Daytona\DTOs\FilePermissionsParams;
use ElliottLawson\Daytona\DTOs\GitBranchesResponse;
use ElliottLawson\Daytona\DTOs\GitHistoryResponse;
use ElliottLawson\Daytona\DTOs\GitStatusResponse;
use ElliottLawson\Daytona\DTOs\ReplaceRequest;
use ElliottLawson\Daytona\DTOs\ReplaceResult;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use ElliottLawson\Daytona\DTOs\SandboxFilter;
use ElliottLawson\Daytona\DTOs\SandboxResponse;
use ElliottLawson\Daytona\DTOs\SearchFilesResponse;
use ElliottLawson\Daytona\DTOs\SearchMatch;
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
            ->acceptJson()
            ->throw(fn ($response, $httpException) => $this->handleApiError($response, $httpException));

        if ($this->config->organizationId) {
            $client->withHeaders([
                'X-Daytona-Organization-ID' => $this->config->organizationId,
            ]);
        }

        return $client;
    }

    /**
     * Centralized API error handling.
     */
    private function handleApiError($response, $httpException): void
    {
        $statusCode = $response->status();
        $body = $response->body();

        // Handle connection timeout errors specifically (not HTTP 504 gateway timeouts)
        if ($httpException && preg_match('/timeout of \d+ms exceeded|cURL error 28/', $httpException->getMessage())) {
            // Extract timeout value from error message if possible
            $timeout = 30; // Default timeout
            if (preg_match('/timeout of (\d+)/', $httpException->getMessage(), $matches)) {
                $timeout = (int) ($matches[1] / 1000); // Convert ms to seconds
            }
            throw ApiException::timeout('API request', $timeout);
        }

        // Enhanced error messages based on status code
        $message = match ($statusCode) {
            401 => 'Authentication failed. Please check your API key.',
            403 => 'Access denied. Please check your permissions.',
            404 => 'Resource not found.',
            409 => 'Conflict - resource already exists or is in use.',
            422 => 'Invalid request data. Please check your parameters.',
            429 => 'Rate limit exceeded. Please try again later.',
            500, 502, 503, 504 => 'Server error. Please try again later.',
            default => "API request failed: {$body}"
        };

        Log::error('API Error', [
            'status' => $statusCode,
            'body' => $body,
            'message' => $message,
        ]);

        $exception = new ApiException($message, $statusCode, $httpException);
        $exception->setResponse($response);

        throw $exception;
    }

    public function createSandbox(SandboxCreateParameters $params): Sandbox
    {
        Log::info('Creating Daytona sandbox', ['params' => $params->toArray()]);

        $response = $this->client()->post('sandbox', $params->toArray());

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
        Log::debug('Getting Daytona sandbox details', ['sandboxId' => $sandboxId]);

        $response = $this->client()->get("sandbox/{$sandboxId}");

        $sandbox = $response->json();

        Log::debug('Sandbox details retrieved', [
            'sandboxId' => $sandboxId,
            'status' => $sandbox['status'] ?? 'unknown',
            'state' => $sandbox['state'] ?? 'unknown',
            'sandbox' => $sandbox,
        ]);

        return SandboxResponse::fromArray($sandbox);
    }

    public function startSandbox(string $sandboxId, ?int $timeout = 60): void
    {
        Log::info('Starting Daytona sandbox', ['sandboxId' => $sandboxId, 'timeout' => $timeout]);

        $response = $this->client()->post("sandbox/{$sandboxId}/start");

        Log::info('Daytona sandbox start request sent', ['sandboxId' => $sandboxId]);

        // Wait until actually started if timeout is specified
        if ($timeout !== null && $timeout > 0) {
            $this->waitUntilSandboxStarted($sandboxId, $timeout);
        }

        Log::info('Daytona sandbox started', ['sandboxId' => $sandboxId]);
    }

    public function stopSandbox(string $sandboxId, ?int $timeout = 60): void
    {
        Log::info('Stopping Daytona sandbox', ['sandboxId' => $sandboxId, 'timeout' => $timeout]);

        $response = $this->client()->post("sandbox/{$sandboxId}/stop");

        Log::info('Daytona sandbox stop request sent', ['sandboxId' => $sandboxId]);

        // Wait until actually stopped if timeout is specified
        if ($timeout !== null && $timeout > 0) {
            $this->waitUntilSandboxStopped($sandboxId, $timeout);
        }

        Log::info('Daytona sandbox stopped', ['sandboxId' => $sandboxId]);
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
            $httpTimeout = $timeout ? (int) ceil($timeout / 1000) + 10 : 300; // Convert ms to seconds + buffer
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
        Log::debug('Reading file from Daytona sandbox', [
            'sandboxId' => $sandboxId,
            'path' => $path,
        ]);

        $response = $this->client()->get("toolbox/{$sandboxId}/toolbox/files/download", [
            'path' => $path,
        ]);

        return $response->body();
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

    /**
     * Create a directory in the sandbox with specified permissions.
     */
    public function createFolder(string $sandboxId, string $path, string $mode): void
    {
        try {
            Log::debug('Creating directory in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'mode' => $mode,
            ]);

            $response = $this->client()->post("toolbox/{$sandboxId}/toolbox/files/folder", [
                'path' => $path,
                'mode' => $mode,
            ]);

            if (! $response->successful()) {
                $error = $response->json('error', 'Unknown error');
                throw FileSystemException::createDirectoryFailed($path, $error);
            }

            Log::debug('Directory created successfully', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'mode' => $mode,
            ]);
        } catch (RequestException $e) {
            Log::error('Failed to create directory in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);
            throw FileSystemException::createDirectoryFailed($path, $e->getMessage(), $e);
        }
    }

    /**
     * Move or rename a file or directory.
     */
    public function moveFile(string $sandboxId, string $source, string $destination): void
    {
        try {
            Log::debug('Moving file in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'source' => $source,
                'destination' => $destination,
            ]);

            $response = $this->client()->post("toolbox/{$sandboxId}/toolbox/files/move", [
                'source' => $source,
                'destination' => $destination,
            ]);

            if (! $response->successful()) {
                $error = $response->json('error', 'Unknown error');
                throw FileSystemException::moveFailed($source, $destination, $error);
            }

            Log::debug('File moved successfully', [
                'sandboxId' => $sandboxId,
                'source' => $source,
                'destination' => $destination,
            ]);
        } catch (RequestException $e) {
            Log::error('Failed to move file in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'source' => $source,
                'destination' => $destination,
                'error' => $e->getMessage(),
            ]);
            throw FileSystemException::moveFailed($source, $destination, $e->getMessage(), $e);
        }
    }

    /**
     * Get detailed file information including permissions, ownership, and metadata.
     */
    public function getFileDetails(string $sandboxId, string $path): FileInfo
    {
        try {
            Log::debug('Getting file details from Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
            ]);

            $response = $this->client()->get("toolbox/{$sandboxId}/toolbox/files/info", [
                'path' => $path,
            ]);

            if (! $response->successful()) {
                $error = $response->json('error', 'Unknown error');
                throw FileSystemException::getFileDetailsFailed($path, $error);
            }

            return FileInfo::fromArray($response->json());
        } catch (RequestException $e) {
            Log::error('Failed to get file details from Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw FileSystemException::getFileDetailsFailed($path, $e->getMessage(), $e);
        }
    }

    /**
     * Set file or directory permissions and ownership.
     */
    public function setFilePermissions(string $sandboxId, string $path, FilePermissionsParams $permissions): void
    {
        try {
            Log::debug('Setting file permissions in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'permissions' => $permissions->toArray(),
            ]);

            $queryParams = ['path' => $path] + $permissions->toArray();

            $response = $this->client()->post("toolbox/{$sandboxId}/toolbox/files/permissions", $queryParams);

            if (! $response->successful()) {
                $error = $response->json('error', 'Unknown error');
                throw FileSystemException::setPermissionsFailed($path, $error);
            }

            Log::debug('File permissions set successfully', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'permissions' => $permissions->toArray(),
            ]);
        } catch (RequestException $e) {
            Log::error('Failed to set file permissions in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'permissions' => $permissions->toArray(),
                'error' => $e->getMessage(),
            ]);
            throw FileSystemException::setPermissionsFailed($path, $e->getMessage(), $e);
        }
    }

    /**
     * Search for files by name pattern (supports glob patterns).
     */
    public function searchFiles(string $sandboxId, string $path, string $pattern): SearchFilesResponse
    {
        try {
            Log::debug('Searching files in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'pattern' => $pattern,
            ]);

            $response = $this->client()->get("toolbox/{$sandboxId}/toolbox/files/search", [
                'path' => $path,
                'pattern' => $pattern,
            ]);

            if (! $response->successful()) {
                $error = $response->json('error', 'Unknown error');
                throw FileSystemException::searchFilesFailed($path, $pattern, $error);
            }

            return SearchFilesResponse::fromArray($response->json());
        } catch (RequestException $e) {
            Log::error('Failed to search files in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
            throw FileSystemException::searchFilesFailed($path, $pattern, $e->getMessage(), $e);
        }
    }

    /**
     * Search for text patterns within files (grep-like functionality).
     *
     * @return SearchMatch[]
     */
    public function findInFiles(string $sandboxId, string $path, string $pattern): array
    {
        try {
            Log::debug('Finding text in files in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'pattern' => $pattern,
            ]);

            $response = $this->client()->get("toolbox/{$sandboxId}/toolbox/files/find", [
                'path' => $path,
                'pattern' => $pattern,
            ]);

            if (! $response->successful()) {
                $error = $response->json('error', 'Unknown error');
                throw FileSystemException::findInFilesFailed($path, $pattern, $error);
            }

            $data = $response->json();

            return array_map(
                fn (array $match) => SearchMatch::fromArray($match),
                $data
            );
        } catch (RequestException $e) {
            Log::error('Failed to find text in files in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'path' => $path,
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
            throw FileSystemException::findInFilesFailed($path, $pattern, $e->getMessage(), $e);
        }
    }

    /**
     * Replace text across multiple files.
     *
     * @param  string[]  $files
     * @return ReplaceResult[]
     */
    public function replaceInFiles(string $sandboxId, array $files, string $pattern, string $newValue): array
    {
        try {
            Log::debug('Replacing text in files in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'files' => $files,
                'pattern' => $pattern,
                'newValue' => $newValue,
            ]);

            $replaceRequest = new ReplaceRequest($files, $pattern, $newValue);

            $response = $this->client()->post("toolbox/{$sandboxId}/toolbox/files/replace", $replaceRequest->toArray());

            if (! $response->successful()) {
                $error = $response->json('error', 'Unknown error');
                throw FileSystemException::replaceInFilesFailed($files, $pattern, $error);
            }

            $data = $response->json();

            return array_map(
                fn (array $result) => ReplaceResult::fromArray($result),
                $data
            );
        } catch (RequestException $e) {
            Log::error('Failed to replace text in files in Daytona sandbox', [
                'sandboxId' => $sandboxId,
                'files' => $files,
                'pattern' => $pattern,
                'newValue' => $newValue,
                'error' => $e->getMessage(),
            ]);
            throw FileSystemException::replaceInFilesFailed($files, $pattern, $e->getMessage(), $e);
        }
    }

    /**
     * List all sandboxes with optional filtering.
     *
     * @param  array|SandboxFilter|null  $filter  Filter criteria for sandboxes
     * @return Sandbox[] Array of Sandbox instances
     */
    public function listSandboxes($filter = null): array
    {
        try {
            Log::debug('Listing Daytona sandboxes', ['filter' => $filter]);

            $queryParams = [];

            if ($filter !== null) {
                if (is_array($filter)) {
                    // Handle legacy array-based labels filter
                    if (! empty($filter)) {
                        $queryParams['labels'] = json_encode($filter);
                    }
                } elseif ($filter instanceof SandboxFilter) {
                    $queryParams = $filter->toArray();
                }
            }

            $response = $this->client()->get('sandbox', $queryParams);

            $sandboxes = $response->json();

            Log::info('Sandboxes listed', ['count' => count($sandboxes)]);

            return array_map(function (array $sandboxData) {
                $sandboxResponse = SandboxResponse::fromArray($sandboxData);

                return new Sandbox($sandboxResponse->id, $this, $sandboxResponse);
            }, $sandboxes);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error during list sandboxes', ['error' => $e->getMessage()]);
            throw ApiException::networkError('list sandboxes', $e);
        } catch (\Exception $e) {
            // Handle timeout exceptions that occur before HTTP response
            if (str_contains($e->getMessage(), 'timeout of') && str_contains($e->getMessage(), 'ms exceeded')) {
                $timeout = 30; // Default timeout
                if (preg_match('/timeout of (\d+)ms/', $e->getMessage(), $matches)) {
                    $timeout = (int) ($matches[1] / 1000); // Convert ms to seconds
                }
                throw ApiException::timeout('list sandboxes', $timeout);
            }
            throw $e;
        }
    }

    /**
     * Find the first sandbox matching the given labels.
     *
     * @param  array  $labels  Labels to match
     * @return Sandbox The first matching sandbox
     *
     * @throws SandboxException When no sandbox is found
     */
    public function findSandboxByLabels(array $labels): Sandbox
    {
        $sandboxes = $this->listSandboxes($labels);

        if (empty($sandboxes)) {
            throw SandboxException::notFound('with labels: '.json_encode($labels));
        }

        return $sandboxes[0];
    }

    /**
     * Find a sandbox by filter criteria.
     *
     * @param  SandboxFilter  $filter  Filter criteria
     * @return Sandbox The first matching sandbox
     *
     * @throws SandboxException When no sandbox is found
     */
    public function findSandbox(SandboxFilter $filter): Sandbox
    {
        $sandboxes = $this->listSandboxes($filter);

        if (empty($sandboxes)) {
            throw SandboxException::notFound('matching filter criteria');
        }

        return $sandboxes[0];
    }

    /**
     * Wait until sandbox reaches the 'started' state.
     */
    public function waitUntilSandboxStarted(string $sandboxId, int $timeout): void
    {
        $this->waitUntilSandboxState($sandboxId, ['started'], ['error', 'failed'], $timeout);
    }

    /**
     * Wait until sandbox reaches the 'stopped' state.
     */
    public function waitUntilSandboxStopped(string $sandboxId, int $timeout): void
    {
        $this->waitUntilSandboxState($sandboxId, ['stopped'], ['error', 'failed'], $timeout);
    }

    /**
     * Generic method to wait until sandbox reaches one of the target states.
     */
    public function waitUntilSandboxState(string $sandboxId, array $targetStates, array $errorStates, int $timeout): void
    {
        $startTime = time();
        $checkInterval = 0.1; // 100ms

        Log::debug('Waiting for sandbox state', [
            'sandboxId' => $sandboxId,
            'targetStates' => $targetStates,
            'timeout' => $timeout,
        ]);

        while (true) {
            $sandbox = $this->getSandbox($sandboxId);

            Log::debug('Checking sandbox state', [
                'sandboxId' => $sandboxId,
                'currentState' => $sandbox->state,
                'targetStates' => $targetStates,
            ]);

            // Check if we've reached a target state
            if (in_array($sandbox->state, $targetStates)) {
                Log::info('Sandbox reached target state', [
                    'sandboxId' => $sandboxId,
                    'state' => $sandbox->state,
                    'elapsedTime' => time() - $startTime,
                ]);

                return;
            }

            // Check if we've reached an error state
            if (in_array($sandbox->state, $errorStates)) {
                Log::error('Sandbox entered error state', [
                    'sandboxId' => $sandboxId,
                    'state' => $sandbox->state,
                    'errorReason' => $sandbox->errorReason,
                ]);
                throw SandboxException::stateError($sandboxId, $sandbox->state, $sandbox->errorReason);
            }

            // Check for timeout
            if ($timeout > 0 && (time() - $startTime) > $timeout) {
                Log::error('Sandbox state timeout', [
                    'sandboxId' => $sandboxId,
                    'currentState' => $sandbox->state,
                    'targetStates' => $targetStates,
                    'timeout' => $timeout,
                ]);
                throw SandboxException::stateTimeout($sandboxId, $targetStates, $timeout);
            }

            // Wait before next check
            usleep($checkInterval * 1000000); // Convert to microseconds
        }
    }
}
