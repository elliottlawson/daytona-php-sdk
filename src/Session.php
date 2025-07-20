<?php

namespace ElliottLawson\Daytona;

use ElliottLawson\Daytona\DTOs\SessionCommandStatus;
use ElliottLawson\Daytona\DTOs\SessionExecuteRequest;
use ElliottLawson\Daytona\DTOs\SessionExecuteResponse;
use ElliottLawson\Daytona\Exceptions\ApiException;

class Session
{
    public function __construct(
        public readonly string $id,
        public readonly string $sandboxId,
        protected DaytonaClient $client
    ) {}

    /**
     * Execute a command in this session.
     *
     * @param string $command The command to execute
     * @param bool $runAsync Whether to run the command asynchronously
     * @param string|null $cwd The working directory for the command
     * @param array|null $env Environment variables for the command
     * @return SessionCommand The command object for tracking execution
     *
     * @throws ApiException If the API request fails
     */
    public function executeCommand(
        string $command,
        bool $runAsync = false,
        ?string $cwd = null,
        ?array $env = null
    ): SessionCommand {
        $request = new SessionExecuteRequest(
            command: $command,
            cwd: $cwd,
            env: $env
        );

        $response = $this->client->executeSessionCommand($this->sandboxId, $this->id, $request);

        return new SessionCommand(
            cmdId: $response->cmdId,
            sessionId: $this->id,
            sandboxId: $this->sandboxId,
            client: $this->client
        );
    }

    /**
     * Get the status of a command executed in this session.
     *
     * @param string $commandId The command ID to check status for
     * @return SessionCommandStatus The command status
     *
     * @throws ApiException If the API request fails
     */
    public function getCommand(string $commandId): SessionCommandStatus
    {
        return $this->client->getSessionCommand($this->sandboxId, $this->id, $commandId);
    }

    /**
     * Stream logs for a command executed in this session.
     *
     * @param string $commandId The command ID to stream logs for
     * @param callable $callback Callback function to receive log chunks
     * @return void
     *
     * @throws ApiException If the API request fails
     */
    public function streamLogs(string $commandId, callable $callback): void
    {
        $this->client->getSessionCommandLogs($this->sandboxId, $this->id, $commandId, $callback);
    }

    /**
     * Get the full logs for a command executed in this session.
     *
     * @param string $commandId The command ID to get logs for
     * @return string The complete logs
     *
     * @throws ApiException If the API request fails
     */
    public function getLogs(string $commandId): string
    {
        return $this->client->getSessionCommandLogs($this->sandboxId, $this->id, $commandId);
    }

    /**
     * Delete this session.
     *
     * @return void
     *
     * @throws ApiException If the API request fails
     */
    public function delete(): void
    {
        $this->client->deleteSession($this->sandboxId, $this->id);
    }
}