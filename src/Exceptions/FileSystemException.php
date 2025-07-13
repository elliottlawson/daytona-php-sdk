<?php

namespace ElliottLawson\Daytona\Exceptions;

use ElliottLawson\Daytona\Exception;

class FileSystemException extends Exception
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
}