<?php

namespace ElliottLawson\Daytona\Exceptions;

use ElliottLawson\Daytona\Exception;
use Illuminate\Http\Client\Response;

class ApiException extends Exception
{
    protected ?Response $response = null;

    public static function fromResponse(Response $response, string $operation): self
    {
        $statusCode = $response->status();
        $body = $response->body();
        
        $message = match ($statusCode) {
            401 => "Authentication failed for {$operation}. Please check your API key.",
            403 => "Access denied for {$operation}. Please check your permissions.",
            404 => "Resource not found for {$operation}.",
            429 => "Rate limit exceeded for {$operation}. Please try again later.",
            500, 502, 503, 504 => "Server error during {$operation}. Please try again later.",
            default => "API request failed for {$operation}: {$body}"
        };

        $exception = new self($message, $statusCode);
        $exception->response = $response;
        
        return $exception;
    }

    public static function networkError(string $operation, ?\Throwable $previous = null): self
    {
        return new self("Network error during {$operation}. Please check your connection.", 0, $previous);
    }

    public static function timeout(string $operation): self
    {
        return new self("Request timed out during {$operation}");
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function getResponseBody(): ?string
    {
        return $this->response?->body();
    }

    public function getStatusCode(): int
    {
        return $this->response?->status() ?? 0;
    }
}