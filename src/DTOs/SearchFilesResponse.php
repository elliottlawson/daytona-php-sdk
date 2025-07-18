<?php

namespace ElliottLawson\Daytona\DTOs;

class SearchFilesResponse
{
    /**
     * @param  string[]  $files
     */
    public function __construct(
        public readonly array $files,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            files: $data['files'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'files' => $this->files,
        ];
    }

    public function getCount(): int
    {
        return count($this->files);
    }

    public function isEmpty(): bool
    {
        return empty($this->files);
    }
}