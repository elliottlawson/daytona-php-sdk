<?php

namespace ElliottLawson\Daytona\DTOs;

class FileInfo
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly bool $isDirectory,
        public readonly ?int $size = null,
        public readonly ?string $modifiedAt = null,
        public readonly ?string $permissions = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            path: $data['path'],
            isDirectory: $data['isDirectory'] ?? $data['is_directory'] ?? false,
            size: $data['size'] ?? null,
            modifiedAt: $data['modifiedAt'] ?? $data['modified_at'] ?? null,
            permissions: $data['permissions'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'path' => $this->path,
            'isDirectory' => $this->isDirectory,
            'size' => $this->size,
            'modifiedAt' => $this->modifiedAt,
            'permissions' => $this->permissions,
        ], fn ($value) => $value !== null);
    }
}
