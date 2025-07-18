<?php

namespace ElliottLawson\Daytona\Exceptions;

use ElliottLawson\Daytona\Exception;

/**
 * Base exception for all Daytona SDK errors.
 * This makes it easy to catch all SDK-related exceptions.
 */
class DaytonaException extends Exception
{
    public static function generic(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }

    public static function notFound(string $resource): self
    {
        return new self("Resource not found: {$resource}");
    }

    public static function timeout(string $operation, int $timeout): self
    {
        return new self("Operation '{$operation}' timed out after {$timeout} seconds");
    }

    public static function invalidArgument(string $argument, string $reason): self
    {
        return new self("Invalid argument '{$argument}': {$reason}");
    }
}
