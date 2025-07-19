<?php

namespace ElliottLawson\Daytona;

use ElliottLawson\Daytona\DTOs\CommandResponse;

class CommandResponseParser
{
    /**
     * Parse command execution response from Daytona API.
     *
     * Handles various response formats that Daytona might return.
     * 
     * Note: When Daytona returns exitCode -1, it means the actual exit code
     * couldn't be determined. Valid bash exit codes are 0-255, so -1 indicates
     * an unknown state rather than a specific failure or success.
     */
    public static function parse(array $result): CommandResponse
    {
        // Handle nested result format
        if (isset($result['exitCode']) && $result['exitCode'] === -1 && isset($result['result'])) {
            if (is_array($result['result']) && isset($result['result']['exitCode'])) {
                // Nested format: extract from result
                return new CommandResponse(
                    exitCode: $result['result']['exitCode'],
                    output: $result['result']['stdout'] ?? $result['result']['output'] ?? '',
                    errorOutput: $result['result']['stderr'] ?? '',
                );
            } elseif (is_string($result['result'])) {
                // When Daytona returns -1, we can't determine the actual exit code
                // Don't assume success or failure
                return new CommandResponse(
                    exitCode: -1, // Keep the -1 to indicate "unknown"
                    output: $result['result'],
                    errorOutput: '',
                );
            }
        }

        // Standard format
        return new CommandResponse(
            exitCode: $result['exitCode'] ?? $result['exit_code'] ?? 0,
            output: $result['stdout'] ?? $result['output'] ?? $result['result'] ?? '',
            errorOutput: $result['stderr'] ?? $result['error'] ?? '',
        );
    }
}
