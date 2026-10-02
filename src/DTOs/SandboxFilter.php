<?php

namespace ElliottLawson\Daytona\DTOs;

class SandboxFilter
{
    /**
     * @param  array|null  $labels  JSON encoded as `labels` query param
     * @param  string|null  $state  Legacy single-state filter (Daytona < 0.180)
     * @param  string|null  $user  Legacy user filter
     * @param  bool|null  $public  Legacy public flag (emits `public` query param)
     * @param  string|null  $name  Filter by name prefix (case-insensitive)
     * @param  string|null  $cursor  Pagination cursor from a previous response
     * @param  int|null  $limit  Number of results per page (1-200)
     * @param  bool|null  $isPublic  Modern public flag (emits `isPublic` query param, Daytona 0.180+)
     * @param  array|null  $states  Modern multi-state filter (Daytona 0.180+)
     */
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?array $labels = null,
        public readonly ?string $state = null,
        public readonly ?string $user = null,
        public readonly ?bool $public = null,
        public readonly ?string $name = null,
        public readonly ?string $cursor = null,
        public readonly ?int $limit = null,
        public readonly ?bool $isPublic = null,
        public readonly ?array $states = null,
    ) {}

    public function toArray(): array
    {
        $filter = [];

        if ($this->id !== null) {
            $filter['id'] = $this->id;
        }

        if ($this->name !== null) {
            $filter['name'] = $this->name;
        }

        if ($this->labels !== null && ! empty($this->labels)) {
            $filter['labels'] = json_encode($this->labels);
        }

        if ($this->state !== null) {
            $filter['state'] = $this->state;
        }

        if ($this->states !== null && ! empty($this->states)) {
            $filter['states'] = array_values($this->states);
        }

        if ($this->user !== null) {
            $filter['user'] = $this->user;
        }

        // Prefer the modern `isPublic` param when explicitly provided, but keep
        // emitting the legacy `public` param for callers built against older APIs.
        if ($this->isPublic !== null) {
            $filter['isPublic'] = $this->isPublic ? 'true' : 'false';
        } elseif ($this->public !== null) {
            $filter['public'] = $this->public ? 'true' : 'false';
        }

        if ($this->cursor !== null) {
            $filter['cursor'] = $this->cursor;
        }

        if ($this->limit !== null) {
            $filter['limit'] = $this->limit;
        }

        return $filter;
    }

    /**
     * Build a copy of this filter with the given fields overridden.
     */
    private function copy(array $overrides = []): self
    {
        return new self(
            id: $overrides['id'] ?? $this->id,
            labels: $overrides['labels'] ?? $this->labels,
            state: $overrides['state'] ?? $this->state,
            user: $overrides['user'] ?? $this->user,
            public: $overrides['public'] ?? $this->public,
            name: $overrides['name'] ?? $this->name,
            cursor: $overrides['cursor'] ?? $this->cursor,
            limit: $overrides['limit'] ?? $this->limit,
            isPublic: $overrides['isPublic'] ?? $this->isPublic,
            states: $overrides['states'] ?? $this->states,
        );
    }

    public static function byLabels(array $labels): self
    {
        return new self(labels: $labels);
    }

    public static function byId(string $id): self
    {
        return new self(id: $id);
    }

    public static function byName(string $name): self
    {
        return new self(name: $name);
    }

    public static function byState(string $state): self
    {
        return new self(state: $state);
    }

    public static function byStates(array $states): self
    {
        return new self(states: $states);
    }

    public static function byUser(string $user): self
    {
        return new self(user: $user);
    }

    public static function byPublic(bool $isPublic): self
    {
        return new self(isPublic: $isPublic);
    }

    public function withLabels(array $labels): self
    {
        return $this->copy([
            'labels' => array_merge($this->labels ?? [], $labels),
        ]);
    }

    public function withState(string $state): self
    {
        return $this->copy(['state' => $state]);
    }

    public function withStates(array $states): self
    {
        return $this->copy(['states' => array_values($states)]);
    }

    public function withUser(string $user): self
    {
        return $this->copy(['user' => $user]);
    }

    public function withName(string $name): self
    {
        return $this->copy(['name' => $name]);
    }

    /**
     * Filter by public visibility using the modern `isPublic` query param.
     */
    public function withPublic(bool $isPublic): self
    {
        return $this->copy(['isPublic' => $isPublic]);
    }

    public function withCursor(string $cursor): self
    {
        return $this->copy(['cursor' => $cursor]);
    }

    public function withLimit(int $limit): self
    {
        return $this->copy(['limit' => $limit]);
    }
}
