<?php

namespace ElliottLawson\Daytona\DTOs;

class Config
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $apiUrl = 'https://app.daytona.io/api',
        public readonly ?string $organizationId = null,
    ) {}
}