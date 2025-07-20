<?php

namespace ElliottLawson\Daytona;

use ElliottLawson\Daytona\DTOs\CommandResponse;
use ElliottLawson\Daytona\DTOs\DirectoryListingResponse;
use ElliottLawson\Daytona\DTOs\FileInfo;
use ElliottLawson\Daytona\DTOs\FilePermissionsParams;
use ElliottLawson\Daytona\DTOs\ReplaceResult;
use ElliottLawson\Daytona\DTOs\SandboxResponse;
use ElliottLawson\Daytona\DTOs\SearchFilesResponse;
use ElliottLawson\Daytona\DTOs\SearchMatch;

class Sandbox
{
    private ?SandboxResponse $data = null;

    public function __construct(
        public readonly string $id,
        protected DaytonaClient $client,
        ?SandboxResponse $initialData = null
    ) {
        $this->data = $initialData;
    }

    public function getId(): string
    {
        return $this->id;
    }

    // Data property accessors
    public function getOrganizationId(): ?string
    {
        return $this->data?->organizationId;
    }

    public function getState(): ?string
    {
        return $this->data?->state;
    }

    public function getDesiredState(): ?string
    {
        return $this->data?->desiredState;
    }

    public function getSnapshot(): ?string
    {
        return $this->data?->snapshot;
    }

    public function getUser(): ?string
    {
        return $this->data?->user;
    }

    public function getRunnerDomain(): ?string
    {
        return $this->data?->runnerDomain;
    }

    public function getEnv(): ?array
    {
        return $this->data?->env;
    }

    public function getLabels(): ?array
    {
        return $this->data?->labels;
    }

    public function getCpu(): ?int
    {
        return $this->data?->cpu;
    }

    public function getMemory(): ?int
    {
        return $this->data?->memory;
    }

    public function getDisk(): ?int
    {
        return $this->data?->disk;
    }

    public function isPublic(): ?bool
    {
        return $this->data?->public;
    }

    public function getCreatedAt(): ?string
    {
        return $this->data?->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->data?->updatedAt;
    }

    public function getData(): ?SandboxResponse
    {
        return $this->data;
    }

    public function toArray(): array
    {
        return $this->data?->toArray() ?? [];
    }

    public function delete(): void
    {
        $this->client->deleteSandbox($this->id);
    }

    public function start(?int $timeout = 60): self
    {
        $this->client->startSandbox($this->id, $timeout);
        $this->refresh();

        return $this;
    }

    public function stop(?int $timeout = 60): self
    {
        $this->client->stopSandbox($this->id, $timeout);
        $this->refresh();

        return $this;
    }

    /**
     * Wait until sandbox reaches the 'started' state.
     */
    public function waitUntilStarted(?int $timeout = 60): self
    {
        if ($timeout !== null) {
            $this->client->waitUntilSandboxStarted($this->id, $timeout);
            $this->refresh();
        }

        return $this;
    }

    /**
     * Wait until sandbox reaches the 'stopped' state.
     */
    public function waitUntilStopped(?int $timeout = 60): self
    {
        if ($timeout !== null) {
            $this->client->waitUntilSandboxStopped($this->id, $timeout);
            $this->refresh();
        }

        return $this;
    }

    /**
     * Refresh sandbox data from the API
     */
    public function refresh(): self
    {
        $this->data = $this->client->getSandbox($this->id);

        return $this;
    }

    public function exec(string $command, ?string $cwd = null, ?array $env = null, ?int $timeout = null): CommandResponse
    {
        return $this->client->executeCommand($this->id, $command, $cwd, $env, $timeout);
    }

    public function readFile(string $path): string
    {
        return $this->client->readFile($this->id, $path);
    }

    public function writeFile(string $path, string $content): self
    {
        $this->client->writeFile($this->id, $path, $content);

        return $this;
    }

    public function listDirectory(string $path): DirectoryListingResponse
    {
        return $this->client->listDirectory($this->id, $path);
    }

    public function deleteFile(string $path): self
    {
        $this->client->deleteFile($this->id, $path);

        return $this;
    }

    public function fileExists(string $path): bool
    {
        return $this->client->fileExists($this->id, $path);
    }

    // Git operations
    public function gitClone(string $url, ?string $branch = null, ?string $path = null, ?string $username = null, ?string $password = null): self
    {
        $this->client->gitClone($this->id, $url, $branch, $path, $username, $password);

        return $this;
    }

    public function gitStatus(string $repoPath = '/workspace'): \ElliottLawson\Daytona\DTOs\GitStatusResponse
    {
        return $this->client->gitStatus($this->id, $repoPath);
    }

    public function gitAdd(string $repoPath, array $filePaths): self
    {
        $this->client->gitAdd($this->id, $repoPath, $filePaths);

        return $this;
    }

    public function gitCommit(string $repoPath, string $message, string $authorName, string $authorEmail): string
    {
        return $this->client->gitCommit($this->id, $repoPath, $message, $authorName, $authorEmail);
    }

    public function gitPush(string $repoPath, ?string $username = null, ?string $password = null): self
    {
        $this->client->gitPush($this->id, $repoPath, $username, $password);

        return $this;
    }

    // New file operations

    /**
     * Create a directory with specified permissions.
     */
    public function createFolder(string $path, string $mode = '755'): self
    {
        $this->client->createFolder($this->id, $path, $mode);

        return $this;
    }

    /**
     * Move or rename a file or directory.
     */
    public function moveFile(string $source, string $destination): self
    {
        $this->client->moveFile($this->id, $source, $destination);

        return $this;
    }

    /**
     * Get detailed file information including permissions, ownership, and metadata.
     */
    public function getFileDetails(string $path): FileInfo
    {
        return $this->client->getFileDetails($this->id, $path);
    }

    /**
     * Set file or directory permissions and ownership.
     */
    public function setFilePermissions(string $path, FilePermissionsParams $permissions): self
    {
        $this->client->setFilePermissions($this->id, $path, $permissions);

        return $this;
    }

    /**
     * Convenience method to set file permissions using individual parameters.
     */
    public function setPermissions(string $path, ?string $mode = null, ?string $owner = null, ?string $group = null): self
    {
        $permissions = new FilePermissionsParams($mode, $owner, $group);

        return $this->setFilePermissions($path, $permissions);
    }

    /**
     * Search for files by name pattern (supports glob patterns).
     */
    public function searchFiles(string $path, string $pattern): SearchFilesResponse
    {
        return $this->client->searchFiles($this->id, $path, $pattern);
    }

    /**
     * Search for text patterns within files (grep-like functionality).
     *
     * @return SearchMatch[]
     */
    public function findInFiles(string $path, string $pattern): array
    {
        return $this->client->findInFiles($this->id, $path, $pattern);
    }

    /**
     * Replace text across multiple files.
     *
     * @param  string[]  $files
     * @return ReplaceResult[]
     */
    public function replaceInFiles(array $files, string $pattern, string $newValue): array
    {
        return $this->client->replaceInFiles($this->id, $files, $pattern, $newValue);
    }

    /**
     * Convenience method to replace text in a single file.
     */
    public function replaceInFile(string $file, string $pattern, string $newValue): ReplaceResult
    {
        $results = $this->replaceInFiles([$file], $pattern, $newValue);

        return $results[0] ?? new ReplaceResult($file, false, 'No result returned');
    }

    /**
     * Get preview URL for a sandbox port.
     *
     * Retrieves the preview link for a sandbox at the specified port.
     * The preview URL allows external access to services running in the sandbox.
     *
     * @param  int  $port  The port number to get preview URL for
     * @return \ElliottLawson\Daytona\DTOs\PortPreviewUrl The preview URL information
     *
     * @throws \ElliottLawson\Daytona\Exceptions\ApiException If the API request fails
     *
     * @example
     * $previewInfo = $sandbox->getPreviewLink(3000);
     * echo "Preview URL: " . $previewInfo->url;
     * echo "Access Token: " . $previewInfo->token;
     */
    public function getPreviewLink(int $port): \ElliottLawson\Daytona\DTOs\PortPreviewUrl
    {
        return $this->client->getPortPreviewUrl($this->id, $port);
    }

    // Session management methods

    /**
     * Create a new session for executing long-running commands.
     *
     * @param  string|null  $sessionId  Optional session ID. If not provided, a UUID will be generated.
     * @return Session The created session instance
     *
     * @throws \ElliottLawson\Daytona\Exceptions\ApiException If the API request fails
     *
     * @example
     * $session = $sandbox->createSession();
     * $command = $session->executeCommand('php -S localhost:8000', true);
     * $session->streamLogs($command->getId(), function($chunk) {
     *     echo $chunk;
     * });
     */
    public function createSession(?string $sessionId = null): Session
    {
        if ($sessionId === null) {
            $sessionId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        }

        $this->client->createSession($this->id, $sessionId);

        return new Session($sessionId, $this->id, $this->client);
    }

    /**
     * Get an existing session by ID.
     *
     * @param  string  $sessionId  The session ID
     * @return Session The session instance
     *
     * @throws \ElliottLawson\Daytona\Exceptions\ApiException If the session doesn't exist
     */
    public function getSession(string $sessionId): Session
    {
        // Verify session exists
        $this->client->getSession($this->id, $sessionId);

        return new Session($sessionId, $this->id, $this->client);
    }

    /**
     * List all active sessions.
     *
     * @return Session[] Array of session instances
     *
     * @throws \ElliottLawson\Daytona\Exceptions\ApiException If the API request fails
     */
    public function listSessions(): array
    {
        $sessionResponses = $this->client->listSessions($this->id);

        return array_map(
            fn ($response) => new Session($response->id, $this->id, $this->client),
            $sessionResponses
        );
    }

    /**
     * Execute a command asynchronously in a temporary session.
     *
     * This is a convenience method that creates a session, executes the command
     * asynchronously, and returns a SessionCommand for tracking.
     *
     * @param  string  $command  The command to execute
     * @param  string|null  $cwd  Working directory
     * @param  array|null  $env  Environment variables
     * @return SessionCommand The command instance for tracking
     *
     * @throws \ElliottLawson\Daytona\Exceptions\ApiException If the API request fails
     *
     * @example
     * $command = $sandbox->execAsync('npm run build');
     * $status = $command->waitForCompletion();
     * echo "Build " . ($status->exitCode === 0 ? "succeeded" : "failed");
     */
    public function execAsync(string $command, ?string $cwd = null, ?array $env = null): SessionCommand
    {
        $sessionId = 'async-exec-'.time().'-'.substr(md5($command), 0, 8);
        $session = $this->createSession($sessionId);

        return $session->executeCommand($command, true, $cwd, $env);
    }
}
