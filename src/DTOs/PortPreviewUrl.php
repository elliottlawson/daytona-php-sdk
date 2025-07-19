<?php

namespace ElliottLawson\Daytona\DTOs;

class PortPreviewUrl
{
    public function __construct(
        public readonly string $url,
        public readonly string $token,
        public readonly ?string $legacyProxyUrl = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'],
            token: $data['token'],
            legacyProxyUrl: $data['legacyProxyUrl'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'url' => $this->url,
            'token' => $this->token,
            'legacyProxyUrl' => $this->legacyProxyUrl,
        ], fn ($value) => $value !== null);
    }
}
