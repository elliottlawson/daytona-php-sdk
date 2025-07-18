<?php

namespace ElliottLawson\Daytona\DTOs;

class FileInfo
{
    public function __construct(
        public readonly string $name,
        public readonly bool $isDirectory,
        public readonly int $size,
        public readonly string $modifiedAt,
        public readonly string $mode,
        public readonly string $permissions,
        public readonly string $owner,
        public readonly string $group,
        public readonly ?string $path = null, // Optional for backward compatibility
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            isDirectory: $data['isDir'] ?? $data['isDirectory'] ?? $data['is_directory'] ?? false,
            size: $data['size'] ?? 0,
            modifiedAt: $data['modTime'] ?? $data['modifiedAt'] ?? $data['modified_at'] ?? '',
            mode: $data['mode'] ?? '',
            permissions: $data['permissions'] ?? '',
            owner: $data['owner'] ?? '',
            group: $data['group'] ?? '',
            path: $data['path'] ?? null,
        );
    }

    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'isDir' => $this->isDirectory,
            'size' => $this->size,
            'modTime' => $this->modifiedAt,
            'mode' => $this->mode,
            'permissions' => $this->permissions,
            'owner' => $this->owner,
            'group' => $this->group,
        ];

        if ($this->path !== null) {
            $result['path'] = $this->path;
        }

        return $result;
    }

    // Backward compatibility getters
    public function getIsDirectory(): bool
    {
        return $this->isDirectory;
    }

    public function getModifiedAt(): string
    {
        return $this->modifiedAt;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }
}
