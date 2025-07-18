<?php

namespace ElliottLawson\Daytona\DTOs;

class Match
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $content,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            file: $data['file'],
            line: $data['line'],
            content: $data['content'],
        );
    }

    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'line' => $this->line,
            'content' => $this->content,
        ];
    }
}