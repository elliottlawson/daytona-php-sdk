<?php

namespace ElliottLawson\Daytona\DTOs;

class SessionExecuteRequest
{
    public function __construct(
        public readonly string $command,
        public readonly bool $runAsync = false,
        public readonly array $env = [],
        public readonly ?string $cwd = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            command: $data['command'],
            runAsync: $data['runAsync'] ?? false,
            env: $data['env'] ?? [],
            cwd: $data['cwd'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'command' => $this->command,
            'runAsync' => $this->runAsync,
            'env' => $this->env,
            'cwd' => $this->cwd,
        ], fn ($value) => $value !== null && $value !== [] && $value !== false);
    }
}