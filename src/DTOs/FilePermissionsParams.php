<?php

namespace ElliottLawson\Daytona\DTOs;

class FilePermissionsParams
{
    public function __construct(
        public readonly ?string $mode = null,
        public readonly ?string $owner = null,
        public readonly ?string $group = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            mode: $data['mode'] ?? null,
            owner: $data['owner'] ?? null,
            group: $data['group'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'mode' => $this->mode,
            'owner' => $this->owner,
            'group' => $this->group,
        ], fn ($value) => $value !== null);
    }

    public function hasMode(): bool
    {
        return $this->mode !== null;
    }

    public function hasOwner(): bool
    {
        return $this->owner !== null;
    }

    public function hasGroup(): bool
    {
        return $this->group !== null;
    }

    public function isEmpty(): bool
    {
        return $this->mode === null && $this->owner === null && $this->group === null;
    }
}
