<?php

namespace ElliottLawson\Daytona;

use ElliottLawson\Daytona\DTOs\CommandResponse;
use ElliottLawson\Daytona\DTOs\DirectoryListingResponse;
use ElliottLawson\Daytona\DTOs\SandboxResponse;

class DaytonaSandbox
{
    public function __construct(
        public readonly string $id,
        protected DaytonaClient $client
    ) {}

    public function getId(): string
    {
        return $this->id;
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

    public function getStatus(): SandboxResponse
    {
        return $this->client->getSandbox($this->id);
    }

    public function executeCommand(string $command, string $cwd = '/workspace'): CommandResponse
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
}