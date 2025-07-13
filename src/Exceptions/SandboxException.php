<?php

namespace ElliottLawson\Daytona\Exceptions;

use ElliottLawson\Daytona\Exception;

class SandboxException extends Exception
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
}