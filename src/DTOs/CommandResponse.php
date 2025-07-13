<?php

namespace ElliottLawson\Daytona\DTOs;

class CommandResponse
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $errorOutput,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            exitCode: $data['exitCode'],
            output: $data['output'],
            errorOutput: $data['errorOutput'],
        );
    }

    public function toArray(): array
    {
        return [
            'exitCode' => $this->exitCode,
            'output' => $this->output,
            'errorOutput' => $this->errorOutput,
        ];
    }

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return ! $this->isSuccessful();
    }

    public function hasOutput(): bool
    {
        return ! empty($this->output);
    }

    public function hasErrorOutput(): bool
    {
        return ! empty($this->errorOutput);
    }
}
