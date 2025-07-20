<?php

namespace ElliottLawson\Daytona\DTOs;

class SessionExecuteResponse
{
    public function __construct(
        public readonly ?string $cmdId = null,
        public readonly ?string $sandboxId = null,
        public readonly ?string $sessionId = null,
        public readonly ?string $output = null,
        public readonly ?int $exitCode = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            cmdId: $data['cmdId'] ?? null,
            sandboxId: $data['sandboxId'] ?? null,
            sessionId: $data['sessionId'] ?? null,
            output: $data['output'] ?? null,
            exitCode: $data['exitCode'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'cmdId' => $this->cmdId,
            'sandboxId' => $this->sandboxId,
            'sessionId' => $this->sessionId,
            'output' => $this->output,
            'exitCode' => $this->exitCode,
        ], fn ($value) => $value !== null);
    }
}
