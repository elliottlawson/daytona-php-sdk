<?php

namespace ElliottLawson\Daytona\DTOs;

class SessionCommandStatus
{
    public function __construct(
        public readonly string $id,
        public readonly string $command,
        public readonly ?int $exitCode = null,
        public readonly ?string $output = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            command: $data['command'],
            exitCode: $data['exitCode'] ?? null,
            output: $data['output'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'command' => $this->command,
            'exitCode' => $this->exitCode,
            'output' => $this->output,
        ], fn ($value) => $value !== null);
    }
}