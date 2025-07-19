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
        public readonly ?bool $branchPublished = null,
    ) {}

    public static function fromArray(array $data): self
    {
        // Parse fileStatus array into staged, unstaged, and untracked arrays
        $staged = [];
        $unstaged = [];
        $untracked = [];
        
        if (isset($data['fileStatus']) && is_array($data['fileStatus'])) {
            foreach ($data['fileStatus'] as $file) {
                $name = $file['name'] ?? '';
                $staging = $file['staging'] ?? '';
                $worktree = $file['worktree'] ?? '';
                
                // Determine file status based on staging and worktree values
                if ($staging === 'Untracked' || $worktree === 'Untracked') {
                    $untracked[] = $name;
                } elseif ($staging === 'Added' || $staging === 'Modified' || $staging === 'Deleted' || $staging === 'Renamed') {
                    $staged[] = $name;
                } elseif ($worktree === 'Modified' || $worktree === 'Deleted') {
                    $unstaged[] = $name;
                }
            }
        }
        
        return new self(
            staged: $staged,
            unstaged: $unstaged,
            untracked: $untracked,
            branch: $data['currentBranch'] ?? null,
            clean: empty($staged) && empty($unstaged) && empty($untracked),
            ahead: $data['ahead'] ?? 0,
            behind: $data['behind'] ?? 0,
            branchPublished: $data['branchPublished'] ?? null,
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
            'branchPublished' => $this->branchPublished,
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
