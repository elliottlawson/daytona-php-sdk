<?php

namespace ElliottLawson\Daytona\DTOs;

class GitCommit
{
    public function __construct(
        public readonly string $hash,
        public readonly string $message,
        public readonly string $author,
        public readonly string $email,
        public readonly string $date,
        public readonly ?string $shortHash = null,
        public readonly ?array $files = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            hash: $data['hash'] ?? $data['sha'],
            message: $data['message'],
            author: $data['author'],
            email: $data['email'],
            date: $data['date'],
            shortHash: $data['shortHash'] ?? $data['short_hash'] ?? null,
            files: $data['files'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'hash' => $this->hash,
            'message' => $this->message,
            'author' => $this->author,
            'email' => $this->email,
            'date' => $this->date,
            'shortHash' => $this->shortHash,
            'files' => $this->files,
        ], fn ($value) => $value !== null);
    }
}
