# Daytona PHP SDK Examples

This directory contains example scripts demonstrating various features of the Daytona PHP SDK.

## Available Examples

### session-example.php
Basic session management example showing:
- Creating and deleting sessions
- Executing synchronous and asynchronous commands
- Managing environment variables within sessions
- Getting command status and logs
- Streaming logs with callbacks

### session-long-running.php
Advanced example demonstrating long-running processes:
- Starting a PHP development server in a session
- Getting preview URLs for running services
- Real-time log streaming from server processes
- Graceful shutdown handling
- Complete lifecycle management

## Running the Examples

1. Set your environment variables:
```bash
export DAYTONA_API_KEY="your-api-key"
export DAYTONA_API_URL="https://api.daytona.io"  # Optional
export DAYTONA_SANDBOX_ID="existing-sandbox-id"  # Optional for some examples
```

2. Run an example:
```bash
php examples/session-example.php
php examples/session-long-running.php
```

## Requirements

- PHP 8.1 or higher
- Composer dependencies installed (`composer install`)
- Valid Daytona API credentials
- Network access to Daytona API

## Notes

- Some examples create sandboxes automatically if `DAYTONA_SANDBOX_ID` is not provided
- Integration tests should be run with caution as they use real API resources
- Preview URLs require proper sandbox configuration and may not be available in all environments