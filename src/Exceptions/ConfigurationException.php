<?php

namespace ElliottLawson\Daytona\Exceptions;

class ConfigurationException extends DaytonaException
{
    public static function missingApiKey(): self
    {
        return new self('Daytona API token is not configured. Please set DAYTONA_API_KEY in your environment or config.');
    }

    public static function missingOrganizationId(): self
    {
        return new self('Daytona organization ID is not configured. Please set DAYTONA_ORGANIZATION_ID in your environment or config.');
    }

    public static function invalidApiUrl(string $url): self
    {
        return new self("Invalid Daytona API URL: {$url}");
    }
}
