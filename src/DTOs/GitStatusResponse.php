<?php

namespace ElliottLawson\Daytona\DTOs;

class GitStatusResponse
{
    /**
     * @param  string[]  $staged
     * @param  string[]  $unstaged
     * @param  string[]  $untracked
     */
    public function __construct(
        public readonly array $staged,
        public readonly array $unstaged,
        public readonly array $untracked,
        public readonly ?string $branch = null,
        public readonly ?bool $clean = null,
        public readonly ?int $ahead = null,
        public readonly ?int $behind = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            staged: $data['staged'] ?? [],
            unstaged: $data['unstaged'] ?? [],
            untracked: $data['untracked'] ?? [],
            branch: $data['branch'] ?? null,
            clean: $data['clean'] ?? null,
            ahead: $data['ahead'] ?? null,
            behind: $data['behind'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'staged' => $this->staged,
            'unstaged' => $this->unstaged,
            'untracked' => $this->untracked,
            'branch' => $this->branch,
            'clean' => $this->clean,
            'ahead' => $this->ahead,
            'behind' => $this->behind,
        ], fn ($value) => $value !== null);
    }

    public function hasChanges(): bool
    {
        return ! empty($this->staged) || ! empty($this->unstaged) || ! empty($this->untracked);
    }

    public function isClean(): bool
    {
        return $this->clean ?? ! $this->hasChanges();
    }
}
