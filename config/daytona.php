<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daytona API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Daytona API credentials and settings here.
    |
    */

    'api_url' => env('DAYTONA_API_URL', 'https://api.daytona.io'),
    
    'api_key' => env('DAYTONA_API_KEY'),
    
    'organization_id' => env('DAYTONA_ORGANIZATION_ID'),
    
    /*
    |--------------------------------------------------------------------------
    | Default Sandbox Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for new sandboxes.
    |
    */
    
    'defaults' => [
        'snapshot' => env('DAYTONA_DEFAULT_SNAPSHOT', 'laravel-php84'),
        'memory' => env('DAYTONA_DEFAULT_MEMORY', 2),
        'disk' => env('DAYTONA_DEFAULT_DISK', 2),
        'cpu' => env('DAYTONA_DEFAULT_CPU', 1),
        'auto_stop_interval' => env('DAYTONA_AUTO_STOP_INTERVAL', 30),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configure timeouts and other HTTP client settings.
    |
    */
    
    'http' => [
        'timeout' => env('DAYTONA_HTTP_TIMEOUT', 30),
        'retry_times' => env('DAYTONA_HTTP_RETRY_TIMES', 3),
        'retry_delay' => env('DAYTONA_HTTP_RETRY_DELAY', 100),
    ],
];