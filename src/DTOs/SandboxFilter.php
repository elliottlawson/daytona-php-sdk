<?php

namespace ElliottLawson\Daytona\DTOs;

class SandboxFilter
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?array $labels = null,
        public readonly ?string $state = null,
        public readonly ?string $user = null,
        public readonly ?bool $public = null,
    ) {}

    public function toArray(): array
    {
        $filter = [];

        if ($this->id !== null) {
            $filter['id'] = $this->id;
        }

        if ($this->labels !== null && ! empty($this->labels)) {
            $filter['labels'] = json_encode($this->labels);
        }

        if ($this->state !== null) {
            $filter['state'] = $this->state;
        }

        if ($this->user !== null) {
            $filter['user'] = $this->user;
        }

        if ($this->public !== null) {
            $filter['public'] = $this->public ? 'true' : 'false';
        }

        return $filter;
    }

    public static function byLabels(array $labels): self
    {
        return new self(labels: $labels);
    }

    public static function byId(string $id): self
    {
        return new self(id: $id);
    }

    public static function byState(string $state): self
    {
        return new self(state: $state);
    }

    public static function byUser(string $user): self
    {
        return new self(user: $user);
    }

    public function withLabels(array $labels): self
    {
        return new self(
            id: $this->id,
            labels: array_merge($this->labels ?? [], $labels),
            state: $this->state,
            user: $this->user,
            public: $this->public,
        );
    }

    public function withState(string $state): self
    {
        return new self(
            id: $this->id,
            labels: $this->labels,
            state: $state,
            user: $this->user,
            public: $this->public,
        );
    }

    public function withUser(string $user): self
    {
        return new self(
            id: $this->id,
            labels: $this->labels,
            state: $this->state,
            user: $user,
            public: $this->public,
        );
    }
}
