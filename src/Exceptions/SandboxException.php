<?php

namespace ElliottLawson\Daytona\Exceptions;

class SandboxException extends DaytonaException
{
    public static function creationFailed(string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to create sandbox: {$message}", 0, $previous);
    }

    public static function invalidResponse(string $reason): self
    {
        return new self("Invalid response from Daytona API: {$reason}");
    }

    public static function notFound(string $sandboxId): self
    {
        return new self("Sandbox not found: {$sandboxId}");
    }

    public static function deletionFailed(string $sandboxId, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to delete sandbox {$sandboxId}: {$message}", 0, $previous);
    }

    public static function startFailed(string $sandboxId, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to start sandbox {$sandboxId}: {$message}", 0, $previous);
    }

    public static function stopFailed(string $sandboxId, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to stop sandbox {$sandboxId}: {$message}", 0, $previous);
    }

    public static function failedToStart(string $sandboxId, string $currentState): self
    {
        return new self("Sandbox {$sandboxId} failed to start. Current state: {$currentState}");
    }

    public static function startTimeout(string $sandboxId, int $timeout): self
    {
        return new self("Sandbox {$sandboxId} failed to start within {$timeout} seconds");
    }

    public static function stopTimeout(string $sandboxId, int $timeout): self
    {
        return new self("Sandbox {$sandboxId} failed to stop within {$timeout} seconds");
    }

    public static function stateTimeout(string $sandboxId, array $targetStates, int $timeout): self
    {
        $statesStr = implode(', ', $targetStates);

        return new self("Sandbox {$sandboxId} failed to reach target state(s) [{$statesStr}] within {$timeout} seconds");
    }

    public static function stateError(string $sandboxId, string $state, ?string $reason = null): self
    {
        $message = "Sandbox {$sandboxId} entered error state: {$state}";
        if ($reason) {
            $message .= " - {$reason}";
        }

        return new self($message);
    }

    public static function unexpectedState(string $sandboxId, string $currentState, array $expectedStates): self
    {
        $expectedStr = implode(', ', $expectedStates);

        return new self("Sandbox {$sandboxId} is in unexpected state '{$currentState}', expected one of: {$expectedStr}");
    }
}
