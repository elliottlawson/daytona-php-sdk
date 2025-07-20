<?php

namespace ElliottLawson\Daytona\DTOs;

class SessionResponse
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $sandboxId = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly array $commands = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            sandboxId: $data['sandboxId'] ?? null,
            createdAt: $data['createdAt'] ?? null,
            updatedAt: $data['updatedAt'] ?? null,
            commands: array_map(
                fn (array $command) => SessionCommandStatus::fromArray($command),
                $data['commands'] ?? []
            ),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'sandboxId' => $this->sandboxId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'commands' => array_map(
                fn (SessionCommandStatus $command) => $command->toArray(),
                $this->commands
            ),
        ], fn ($value) => $value !== null);
    }
}
