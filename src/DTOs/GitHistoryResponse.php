<?php

namespace ElliottLawson\Daytona\DTOs;

class GitHistoryResponse
{
    /**
     * @param GitCommit[] $commits
     */
    public function __construct(
        public readonly array $commits,
    ) {}

    public static function fromArray(array $data): self
    {
        $commits = array_map(
            fn(array $commit) => GitCommit::fromArray($commit),
            $data['commits'] ?? $data
        );

        return new self(commits: $commits);
    }

    public function toArray(): array
    {
        return [
            'commits' => array_map(
                fn(GitCommit $commit) => $commit->toArray(),
                $this->commits
            ),
        ];
    }

    public function getLatestCommit(): ?GitCommit
    {
        return $this->commits[0] ?? null;
    }
}