<?php

namespace ElliottLawson\Daytona;

use ElliottLawson\Daytona\DTOs\CommandResponse;

class CommandResponseParser
{
    /**
     * Parse command execution response from Daytona API.
     *
     * Handles various response formats that Daytona might return.
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
                // String result with -1 exit code
                return new CommandResponse(
                    exitCode: 0, // Assume success if we got string output
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
