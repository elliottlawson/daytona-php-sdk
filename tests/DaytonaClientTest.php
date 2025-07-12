<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\Exceptions\DaytonaException;

it('can be instantiated with config', function () {
    $client = new DaytonaClient([
        'api_url' => 'https://api.daytona.io',
        'api_key' => 'test-key',
    ]);

    expect($client)->toBeInstanceOf(DaytonaClient::class);
});

it('throws exception when api key is missing', function () {
    new DaytonaClient([
        'api_url' => 'https://api.daytona.io',
    ]);
})->throws(DaytonaException::class, 'Daytona API token is not configured');

it('can be resolved from container in Laravel', function () {
    $client = app(DaytonaClient::class);
    
    expect($client)->toBeInstanceOf(DaytonaClient::class);
});