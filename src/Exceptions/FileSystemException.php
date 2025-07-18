<?php

namespace ElliottLawson\Daytona\Exceptions;

class FileSystemException extends DaytonaException
{
    public static function readFailed(string $path, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to read file '{$path}': {$message}", 0, $previous);
    }

    public static function writeFailed(string $path, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to write file '{$path}': {$message}", 0, $previous);
    }

    public static function deleteFailed(string $path, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to delete file '{$path}': {$message}", 0, $previous);
    }

    public static function listDirectoryFailed(string $path, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to list directory '{$path}': {$message}", 0, $previous);
    }

    public static function fileNotFound(string $path): self
    {
        return new self("File not found: {$path}");
    }

    public static function accessDenied(string $path): self
    {
        return new self("Access denied to file: {$path}");
    }

    public static function checkExistenceFailed(string $path, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to check file existence for '{$path}': {$message}", 0, $previous);
    }

    public static function createDirectoryFailed(string $path, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to create directory '{$path}': {$message}", 0, $previous);
    }

    public static function moveFailed(string $source, string $destination, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to move '{$source}' to '{$destination}': {$message}", 0, $previous);
    }

    public static function getFileDetailsFailed(string $path, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to get file details for '{$path}': {$message}", 0, $previous);
    }

    public static function setPermissionsFailed(string $path, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to set permissions for '{$path}': {$message}", 0, $previous);
    }

    public static function searchFilesFailed(string $path, string $pattern, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to search files in '{$path}' with pattern '{$pattern}': {$message}", 0, $previous);
    }

    public static function findInFilesFailed(string $path, string $pattern, string $message, ?\Throwable $previous = null): self
    {
        return new self("Failed to find text in files in '{$path}' with pattern '{$pattern}': {$message}", 0, $previous);
    }

    /**
     * @param  string[]  $files
     */
    public static function replaceInFilesFailed(array $files, string $pattern, string $message, ?\Throwable $previous = null): self
    {
        $filesList = implode(', ', array_slice($files, 0, 3)).(count($files) > 3 ? '...' : '');

        return new self("Failed to replace text in files [{$filesList}] with pattern '{$pattern}': {$message}", 0, $previous);
    }
}
