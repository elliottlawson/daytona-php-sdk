<?php

namespace ElliottLawson\Daytona;

use ElliottLawson\Daytona\DTOs\CommandResponse;
use ElliottLawson\Daytona\DTOs\DirectoryListingResponse;
use ElliottLawson\Daytona\DTOs\SandboxResponse;

// use Illuminate\Support\Sleep;

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

    public function start(): void
    {
        $this->client->startSandbox($this->id);
    }

    public function stop(): void
    {
        $this->client->stopSandbox($this->id);
    }

    /**
     * Refresh sandbox data from the API
     */
    public function refresh(): self
    {
        $this->data = $this->client->getSandbox($this->id);

        return $this;
    }

    public function exec(string $command, string $cwd = '/workspace'): CommandResponse
    {
        return $this->client->executeCommand($this->id, $command, $cwd);
    }

    public function readFile(string $path): string
    {
        return $this->client->readFile($this->id, $path);
    }

    public function writeFile(string $path, string $content): void
    {
        $this->client->writeFile($this->id, $path, $content);
    }

    public function listDirectory(string $path): DirectoryListingResponse
    {
        return $this->client->listDirectory($this->id, $path);
    }

    public function deleteFile(string $path): void
    {
        $this->client->deleteFile($this->id, $path);
    }

    public function fileExists(string $path): bool
    {
        return $this->client->fileExists($this->id, $path);
    }

    // Git operations
    public function gitClone(string $url, ?string $branch = null, string $path = '/workspace', ?string $username = null, ?string $password = null): self
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
}
