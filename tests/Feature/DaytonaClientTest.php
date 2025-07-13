<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\Exceptions\ConfigurationException;

it('can be instantiated with config', function () {
    $config = new Config(
        apiKey: 'test-key',
        apiUrl: 'https://api.daytona.io',
        organizationId: 'test-org'
    );
    $client = new DaytonaClient($config);

    expect($client)->toBeInstanceOf(DaytonaClient::class);
});

it('can be instantiated from Laravel config', function () {
    // Ensure config is set
    config(['daytona.api_key' => 'test-key']);
    config(['daytona.api_url' => 'https://api.daytona.io']);
    config(['daytona.organization_id' => 'test-org']);
    
    $config = app(Config::class);
    $client = new DaytonaClient($config);
    
    expect($client)->toBeInstanceOf(DaytonaClient::class);
});

it('can be instantiated with typed configuration', function () {
    $config = new \ElliottLawson\Daytona\DTOs\Config(
        apiUrl: 'https://api.daytona.io',
        apiKey: 'test-key',
        organizationId: 'test-org'
    );
    
    $client = new DaytonaClient($config);
    
    expect($client)->toBeInstanceOf(DaytonaClient::class);
});

it('throws exception when api key is missing', function () {
    $config = new Config(
        apiKey: '',
        apiUrl: 'https://api.daytona.io',
        organizationId: 'test-org'
    );
    new DaytonaClient($config);
})->throws(ConfigurationException::class, 'Daytona API token is not configured');

it('can be resolved from container in Laravel', function () {
    $client = app(DaytonaClient::class);
    
    expect($client)->toBeInstanceOf(DaytonaClient::class);
});