<?php

namespace ElliottLawson\Daytona\Exceptions;

use Illuminate\Http\Client\Response;

class ApiException extends DaytonaException
{
    protected ?Response $response = null;

    public static function fromResponse(Response $response, string $operation): self
    {
        $statusCode = $response->status();
        $body = $response->body();

        $message = match ($statusCode) {
            401 => 'Authentication failed. Please check your API key.',
            403 => 'Access denied. Please check your permissions.',
            404 => 'Resource not found.',
            409 => 'Conflict - resource already exists or is in use.',
            422 => 'Invalid request data. Please check your parameters.',
            429 => 'Rate limit exceeded. Please try again later.',
            500, 502, 503, 504 => 'Server error. Please try again later.',
            default => "API request failed: {$body}"
        };

        $exception = new self($message, $statusCode);
        $exception->response = $response;

        return $exception;
    }

    public static function networkError(string $operation, ?\Throwable $previous = null): self
    {
        return new self("Network error during {$operation}. Please check your connection.", 0, $previous);
    }

    public static function timeout(string $operation, int $timeout = 30): self
    {
        return new self("Request timed out during {$operation} after {$timeout} seconds");
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
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
