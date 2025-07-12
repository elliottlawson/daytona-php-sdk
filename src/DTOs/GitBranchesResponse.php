<?php

namespace ElliottLawson\Daytona\DTOs;

class GitBranchesResponse
{
    /**
     * @param string[] $branches
     */
    public function __construct(
        public readonly array $branches,
        public readonly ?string $currentBranch = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            branches: $data['branches'] ?? $data,
            currentBranch: $data['currentBranch'] ?? $data['current_branch'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'branches' => $this->branches,
            'currentBranch' => $this->currentBranch,
        ], fn($value) => $value !== null);
    }

    public function hasBranch(string $branchName): bool
    {
        return in_array($branchName, $this->branches, true);
    }
}