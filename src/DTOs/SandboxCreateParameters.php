<?php

namespace ElliottLawson\Daytona\DTOs;

class SandboxCreateParameters
{
    public function __construct(
        // Base parameters (from TypeScript SDK)
        public ?string $user = null,
        public ?string $language = null,
        public ?array $envVars = null,
        public ?array $labels = null,
        public ?bool $public = null,
        public ?int $autoStopInterval = null,
        public ?int $autoArchiveInterval = null,
        public ?int $autoDeleteInterval = null,
        public ?array $volumes = null,

        // Snapshot-specific
        public ?string $snapshot = null,

        // Image-specific
        public ?string $image = null,

        // Resources (for image-based creation)
        public ?int $cpu = null,
        public ?int $gpu = null,
        public ?int $memory = null,
        public ?int $disk = null,

        // Additional fields from API response
        public ?string $target = null,
        public ?string $class = null,
    ) {}

    public function toArray(): array
    {
        $data = [];

        // Map envVars to env for API compatibility
        if ($this->envVars !== null) {
            $data['env'] = $this->envVars;
        }

        // Add other fields
        $fields = [
            'user' => $this->user,
            'language' => $this->language,
            'labels' => $this->labels,
            'public' => $this->public,
            'autoStopInterval' => $this->autoStopInterval,
            'autoArchiveInterval' => $this->autoArchiveInterval,
            'autoDeleteInterval' => $this->autoDeleteInterval,
            'volumes' => $this->volumes,
            'snapshot' => $this->snapshot,
            'image' => $this->image,
            'target' => $this->target,
            'class' => $this->class,
        ];

        // API restriction: Cannot specify resources when using a snapshot
        if ($this->snapshot === null) {
            $fields['cpu'] = $this->cpu;
            $fields['gpu'] = $this->gpu;
            $fields['memory'] = $this->memory;
            $fields['disk'] = $this->disk;
        }

        foreach ($fields as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
