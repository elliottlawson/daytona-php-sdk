<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use ElliottLawson\Daytona\Sandbox;

uses(\Tests\IntegrationTestCase::class);

beforeEach(function () {
    $this->client = resolve(DaytonaClient::class);
});

it('creates a sandbox with minimal parameters and validates response structure', function () {
    $params = new SandboxCreateParameters;

    $sandbox = $this->client->createSandbox($params);

    expect($sandbox)->toBeInstanceOf(Sandbox::class);
    expect($sandbox->getId())->toBeString()->not->toBeEmpty();
    expect($sandbox->getOrganizationId())->toBeString();
    expect($sandbox->getState())->toBeIn(['started', 'starting', 'stopped', 'stopping']);
    expect($sandbox->getCreatedAt())->toBeString();
    expect($sandbox->getUpdatedAt())->toBeString();

    $sandbox->delete();
});

it('creates a sandbox with custom parameters', function () {
    $params = new SandboxCreateParameters(
        user: 'daytona',
        language: 'php',
        envVars: ['NODE_ENV' => 'development', 'DEBUG' => 'true'],
        labels: ['environment' => 'testing', 'project' => 'php-sdk'],
        public: false,
        autoStopInterval: 30,
        autoArchiveInterval: 10080,
        autoDeleteInterval: -1,
        snapshot: 'daytonaio/sandbox:0.4.3',
        target: 'us',
        class: 'medium',
    );

    $sandbox = $this->client->createSandbox($params);

    expect($sandbox)->toBeInstanceOf(Sandbox::class);
    expect($sandbox->getId())->toBeString()->not->toBeEmpty();

    // Validate that our custom parameters were applied (if API supports them)
    if ($sandbox->getData()?->class !== null) {
        expect($sandbox->getData()->class)->toBe('medium');
    }
    if ($sandbox->getData()?->target !== null) {
        expect($sandbox->getData()->target)->toBe('us');
    }

    $sandbox->delete();
});

it('validates all fields in sandbox response match expected types', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters);
    $response = $sandbox->getData();

    // Validate field types
    expect($sandbox->getId())->toBeString();

    if ($response->organizationId !== null) {
        expect($response->organizationId)->toBeString();
    }

    if ($response->target !== null) {
        expect($response->target)->toBeString();
    }

    if ($response->snapshot !== null) {
        expect($response->snapshot)->toBeString();
    }

    if ($response->user !== null) {
        expect($response->user)->toBeString();
    }

    if ($response->env !== null) {
        expect($response->env)->toBeArray();
    }

    if ($response->cpu !== null) {
        expect($response->cpu)->toBeInt();
    }

    if ($response->gpu !== null) {
        expect($response->gpu)->toBeInt();
    }

    if ($response->memory !== null) {
        expect($response->memory)->toBeInt();
    }

    if ($response->disk !== null) {
        expect($response->disk)->toBeInt();
    }

    if ($response->public !== null) {
        expect($response->public)->toBeBool();
    }

    if ($response->labels !== null) {
        expect($response->labels)->toBeArray();
    }

    if ($response->volumes !== null) {
        expect($response->volumes)->toBeArray();
    }

    if ($response->state !== null) {
        expect($response->state)->toBeString();
    }

    if ($response->desiredState !== null) {
        expect($response->desiredState)->toBeString();
    }

    if ($response->backupState !== null) {
        expect($response->backupState)->toBeString();
    }

    if ($response->autoStopInterval !== null) {
        expect($response->autoStopInterval)->toBeInt();
    }

    if ($response->autoArchiveInterval !== null) {
        expect($response->autoArchiveInterval)->toBeInt();
    }

    if ($response->autoDeleteInterval !== null) {
        expect($response->autoDeleteInterval)->toBeInt();
    }

    if ($response->class !== null) {
        expect($response->class)->toBeString();
    }

    if ($response->createdAt !== null) {
        expect($response->createdAt)->toBeString();
    }

    if ($response->updatedAt !== null) {
        expect($response->updatedAt)->toBeString();
    }

    if ($response->runnerDomain !== null) {
        expect($response->runnerDomain)->toBeString();
    }

    $sandbox->delete();
});

it('can retrieve sandbox details after creation', function () {
    $createdSandbox = $this->client->createSandbox(new SandboxCreateParameters);

    $getResponse = $this->client->getSandbox($createdSandbox->getId());

    expect($getResponse)->toBeInstanceOf(\ElliottLawson\Daytona\DTOs\SandboxResponse::class);
    expect($getResponse->id)->toBe($createdSandbox->getId());
    expect($getResponse->organizationId)->toBe($createdSandbox->getOrganizationId());

    $createdSandbox->delete();
});

it('validates sandbox lifecycle operations', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        language: 'php',
        envVars: ['TEST_ENV' => 'lifecycle_test'],
    ));

    // Stop the sandbox if it's running
    if ($sandbox->getState() === 'started') {
        $sandbox->stop();

        // Wait a moment for state change
        sleep(2);

        $sandbox->refresh();
        expect($sandbox->getState())->toBeIn(['stopped', 'stopping']);
    }

    // Start the sandbox
    $sandbox->start();

    // Wait a moment for state change
    sleep(2);

    $sandbox->refresh();
    expect($sandbox->getState())->toBeIn(['started', 'starting']);

    $sandbox->delete();
});
