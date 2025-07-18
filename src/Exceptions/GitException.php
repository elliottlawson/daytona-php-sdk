<?php

namespace ElliottLawson\Daytona\Exceptions;

class GitException extends DaytonaException
{
    public static function cloneFailed(string $url, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to clone repository '{$url}': {$message}", 0, $previous);
    }

    public static function branchListFailed(string $repoPath, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to list branches in '{$repoPath}': {$message}", 0, $previous);
    }

    public static function addFailed(array $files, string $message, ?\Throwable $previous = null): self
    {
        $fileList = implode(', ', $files);

        return new self("Failed to add files to Git [{$fileList}]: {$message}", 0, $previous);
    }

    public static function commitFailed(string $message, string $error, ?\Throwable $previous = null): self
    {
        return new self("Failed to commit changes '{$message}': {$error}", 0, $previous);
    }

    public static function pushFailed(string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to push changes: {$message}", 0, $previous);
    }

    public static function statusFailed(string $repoPath, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to get Git status for '{$repoPath}': {$message}", 0, $previous);
    }

    public static function historyFailed(string $repoPath, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to get Git history for '{$repoPath}': {$message}", 0, $previous);
    }

    public static function authenticationFailed(string $url): self
    {
        return new self("Git authentication failed for repository: {$url}");
    }

    public static function invalidRepository(string $path): self
    {
        return new self("Invalid Git repository at: {$path}");
    }
}
