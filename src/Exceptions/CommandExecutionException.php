<?php

namespace ElliottLawson\Daytona\Exceptions;

class CommandExecutionException extends DaytonaException
{
    public static function executionFailed(string $command, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to execute command '{$command}': {$message}", 0, $previous);
    }

    public static function nonZeroExitCode(string $command, int $exitCode, string $stderr): self
    {
        return new self(
            "Command '{$command}' failed with exit code {$exitCode}. Error: {$stderr}"
        );
    }

    public static function timeout(string $command, int $timeout): self
    {
        return new self("Command '{$command}' timed out after {$timeout} seconds");
    }

    public static function invalidCommand(string $command): self
    {
        return new self("Invalid command: {$command}");
    }
}
