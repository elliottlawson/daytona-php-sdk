<?php

namespace ElliottLawson\Daytona;

use ElliottLawson\Daytona\DTOs\SessionCommandStatus;
use ElliottLawson\Daytona\Exceptions\ApiException;
use ElliottLawson\Daytona\Exceptions\CommandExecutionException;

class SessionCommand
{
    public function __construct(
        public readonly string $cmdId,
        public readonly string $sessionId,
        public readonly string $sandboxId,
        protected DaytonaClient $client
    ) {}

    /**
     * Get the command ID.
     *
     * @return string The command ID
     */
    public function getId(): string
    {
        return $this->cmdId;
    }

    /**
     * Get the current status of this command.
     *
     * @return SessionCommandStatus The command status
     *
     * @throws ApiException If the API request fails
     */
    public function getStatus(): SessionCommandStatus
    {
        return $this->client->getSessionCommand($this->sandboxId, $this->sessionId, $this->cmdId);
    }

    /**
     * Stream logs for this command.
     *
     * @param  callable  $callback  Callback function to receive log chunks
     *
     * @throws ApiException If the API request fails
     */
    public function streamLogs(callable $callback): void
    {
        $this->client->getSessionCommandLogs($this->sandboxId, $this->sessionId, $this->cmdId, $callback);
    }

    /**
     * Get the full logs for this command.
     *
     * @return string The complete logs
     *
     * @throws ApiException If the API request fails
     */
    public function getLogs(): string
    {
        return $this->client->getSessionCommandLogs($this->sandboxId, $this->sessionId, $this->cmdId);
    }

    /**
     * Wait for the command to complete.
     *
     * @param  int  $timeout  Maximum time to wait in seconds (default: 300)
     * @return SessionCommandStatus The final command status
     *
     * @throws CommandExecutionException If the command times out
     * @throws ApiException If the API request fails
     */
    public function waitForCompletion(int $timeout = 300): SessionCommandStatus
    {
        $startTime = time();
        $pollInterval = 0.5; // Poll every 500ms

        while (true) {
            $status = $this->getStatus();

            // Check if command has completed (exitCode is set)
            if ($status->exitCode !== null) {
                return $status;
            }

            // Check if we've exceeded the timeout
            if (time() - $startTime > $timeout) {
                throw new CommandExecutionException(
                    "Command execution timed out after {$timeout} seconds"
                );
            }

            // Sleep before next poll
            usleep((int) ($pollInterval * 1000000));
        }
    }
}
