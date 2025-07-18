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

    public function gitCommit(string $repoPath, string $message, string $authorName, string $authorEmail): self
    {
        $this->client->gitCommit($this->id, $repoPath, $message, $authorName, $authorEmail);

        return $this;
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
}
