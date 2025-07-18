<?php

namespace ElliottLawson\Daytona\DTOs;

class ReplaceResult
{
    public function __construct(
        public readonly ?string $file = null,
        public readonly ?bool $success = null,
        public readonly ?string $error = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            file: $data['file'] ?? null,
            success: $data['success'] ?? null,
            error: $data['error'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'file' => $this->file,
            'success' => $this->success,
            'error' => $this->error,
        ], fn ($value) => $value !== null);
    }

    public function isSuccess(): bool
    {
        return $this->success === true;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }
}