<?php

namespace ElliottLawson\Daytona\DTOs;

class SandboxResponse
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $organizationId = null,
        public readonly ?string $target = null,
        public readonly ?string $snapshot = null,
        public readonly ?string $user = null,
        public readonly ?array $env = null,
        public readonly ?int $cpu = null,
        public readonly ?int $gpu = null,
        public readonly ?int $memory = null,
        public readonly ?int $disk = null,
        public readonly ?bool $public = null,
        public readonly ?array $labels = null,
        public readonly ?array $volumes = null,
        public readonly ?string $state = null,
        public readonly ?string $desiredState = null,
        public readonly ?string $backupState = null,
        public readonly ?int $autoStopInterval = null,
        public readonly ?int $autoArchiveInterval = null,
        public readonly ?int $autoDeleteInterval = null,
        public readonly ?string $class = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly ?string $runnerDomain = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            organizationId: $data['organizationId'] ?? null,
            target: $data['target'] ?? null,
            snapshot: $data['snapshot'] ?? null,
            user: $data['user'] ?? null,
            env: $data['env'] ?? null,
            cpu: $data['cpu'] ?? null,
            gpu: $data['gpu'] ?? null,
            memory: $data['memory'] ?? null,
            disk: $data['disk'] ?? null,
            public: $data['public'] ?? null,
            labels: $data['labels'] ?? null,
            volumes: $data['volumes'] ?? null,
            state: $data['state'] ?? null,
            desiredState: $data['desiredState'] ?? null,
            backupState: $data['backupState'] ?? null,
            autoStopInterval: $data['autoStopInterval'] ?? null,
            autoArchiveInterval: $data['autoArchiveInterval'] ?? null,
            autoDeleteInterval: $data['autoDeleteInterval'] ?? null,
            class: $data['class'] ?? null,
            createdAt: $data['createdAt'] ?? null,
            updatedAt: $data['updatedAt'] ?? null,
            runnerDomain: $data['runnerDomain'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'organizationId' => $this->organizationId,
            'target' => $this->target,
            'snapshot' => $this->snapshot,
            'user' => $this->user,
            'env' => $this->env,
            'cpu' => $this->cpu,
            'gpu' => $this->gpu,
            'memory' => $this->memory,
            'disk' => $this->disk,
            'public' => $this->public,
            'labels' => $this->labels,
            'volumes' => $this->volumes,
            'state' => $this->state,
            'desiredState' => $this->desiredState,
            'backupState' => $this->backupState,
            'autoStopInterval' => $this->autoStopInterval,
            'autoArchiveInterval' => $this->autoArchiveInterval,
            'autoDeleteInterval' => $this->autoDeleteInterval,
            'class' => $this->class,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'runnerDomain' => $this->runnerDomain,
        ], fn($value) => $value !== null);
    }
}