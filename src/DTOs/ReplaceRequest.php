<?php

namespace ElliottLawson\Daytona\DTOs;

class ReplaceRequest
{
    /**
     * @param  string[]  $files
     */
    public function __construct(
        public readonly array $files,
        public readonly string $pattern,
        public readonly string $newValue,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            files: $data['files'],
            pattern: $data['pattern'],
            newValue: $data['newValue'],
        );
    }

    public function toArray(): array
    {
        return [
            'files' => $this->files,
            'pattern' => $this->pattern,
            'newValue' => $this->newValue,
        ];
    }
}