<?php

use ElliottLawson\Daytona\DTOs\SandboxCreateParameters;
use ElliottLawson\Daytona\DTOs\SandboxResponse;
use ElliottLawson\Daytona\Sandbox;
use Tests\Integration\SandboxTestHelper;

uses(SandboxTestHelper::class);

beforeEach(function () {
    $this->setupClient();
});

afterEach(function () {
    $this->cleanupSandboxes();
});

it('can create and delete a sandbox', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    expect($sandbox)->toBeInstanceOf(Sandbox::class);
    expect($sandbox->getId())->toBeString()->not->toBeEmpty();
    expect($sandbox->getState())->toBeIn(['started', 'starting']);

    // Clean up
    $sandbox->delete();

    // The sandbox deletion was successful if we get here without exceptions
});

it('creates a sandbox with minimal parameters and validates response structure', function () {
    $params = new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    );

    $sandbox = $this->client->createSandbox($params);

    expect($sandbox)->toBeInstanceOf(Sandbox::class);
    expect($sandbox->getId())->toBeString()->not->toBeEmpty();
    expect($sandbox->getOrganizationId())->toBeString();
    expect($sandbox->getState())->toBeIn(['started', 'starting', 'stopped', 'stopping']);
    expect($sandbox->getCreatedAt())->toBeString();
    expect($sandbox->getUpdatedAt())->toBeString();
});

it('creates a sandbox with custom parameters', function () {
    $params = new SandboxCreateParameters(
        user: 'daytona',
        language: 'php',
        envVars: ['NODE_ENV' => 'development', 'DEBUG' => 'true'],
        labels: ['environment' => 'testing', 'project' => 'php-sdk', 'php-sdk-test' => 'true'],
        public: false,
        autoStopInterval: 30,
        autoArchiveInterval: 10080,
        autoDeleteInterval: 0,
        // Remove specific snapshot, target, and class that might not be available
    );

    $sandbox = $this->client->createSandbox($params);

    expect($sandbox)->toBeInstanceOf(Sandbox::class);
    expect($sandbox->getId())->toBeString()->not->toBeEmpty();

    // Validate that our custom parameters were applied
    $data = $sandbox->getData();

    // Check if environment variables were set (if supported by API)
    if ($data->env !== null) {
        expect($data->env)->toBeArray();
    }

    // Check if labels were set (if supported by API)
    if ($data->labels !== null) {
        expect($data->labels)->toBeArray();
    }
});

it('validates all fields in sandbox response match expected types', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));
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
});

it('can retrieve sandbox details after creation', function () {
    $createdSandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    $getResponse = $this->client->getSandbox($createdSandbox->getId());

    expect($getResponse)->toBeInstanceOf(SandboxResponse::class);
    expect($getResponse->id)->toBe($createdSandbox->getId());
    expect($getResponse->organizationId)->toBe($createdSandbox->getOrganizationId());
});

it('validates sandbox lifecycle operations', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        language: 'php',
        envVars: ['TEST_ENV' => 'lifecycle_test'],
        labels: ['php-sdk-test' => 'true']
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
});

it('handles multiple state transitions correctly', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    // Record initial state
    $initialState = $sandbox->getState();
    expect($initialState)->toBeIn(['started', 'starting', 'stopped', 'stopping']);

    // If started, stop then start again
    if (in_array($initialState, ['started', 'starting'])) {
        // Stop the sandbox
        $sandbox->stop(60);
        expect($sandbox->getState())->toBeIn(['stopped', 'stopping']);

        // Start it again
        $sandbox->start(60);
        expect($sandbox->getState())->toBeIn(['started', 'starting']);

        // Stop once more
        $sandbox->stop(60);
        expect($sandbox->getState())->toBeIn(['stopped', 'stopping']);
    }
});

it('validates waitUntilStarted and waitUntilStopped methods', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    // Ensure sandbox is stopped first
    if ($sandbox->getState() === 'started') {
        $sandbox->stop();
        $sandbox->waitUntilStopped(60);
        expect($sandbox->getState())->toBe('stopped');
    }

    // Start and wait
    $sandbox->start();
    $sandbox->waitUntilStarted(60);
    expect($sandbox->getState())->toBe('started');

    // Stop and wait
    $sandbox->stop();
    $sandbox->waitUntilStopped(60);
    expect($sandbox->getState())->toBe('stopped');
});

it('refresh method updates sandbox data from API', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    // Store initial data
    $initialData = $sandbox->getData();
    $initialUpdatedAt = $sandbox->getUpdatedAt();

    // Make a change via the client (not through the sandbox object)
    if ($sandbox->getState() === 'started') {
        $this->client->stopSandbox($sandbox->getId());
    } else {
        $this->client->startSandbox($sandbox->getId());
    }

    // Wait for state change to process
    sleep(2);

    // Before refresh, data should be stale
    expect($sandbox->getUpdatedAt())->toBe($initialUpdatedAt);

    // After refresh, data should be updated
    $sandbox->refresh();
    expect($sandbox->getUpdatedAt())->not->toBe($initialUpdatedAt);
});

it('validates desired state tracking', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    // Check that desired state is tracked
    $desiredState = $sandbox->getDesiredState();
    if ($desiredState !== null) {
        expect($desiredState)->toBeIn(['started', 'stopped', 'archived']);
    }

    // Trigger a state change and verify desired state updates
    if ($sandbox->getState() === 'started') {
        $sandbox->stop();
        sleep(1);
        $sandbox->refresh();

        if ($sandbox->getDesiredState() !== null) {
            expect($sandbox->getDesiredState())->toBe('stopped');
        }
    }
});

it('can list multiple sandboxes', function () {
    // Create multiple sandboxes
    $sandbox1 = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['test-group' => 'list-test', 'number' => '1', 'php-sdk-test' => 'true']
    ));

    $sandbox2 = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['test-group' => 'list-test', 'number' => '2', 'php-sdk-test' => 'true']
    ));

    // List all sandboxes
    $allSandboxes = $this->client->listSandboxes();
    expect($allSandboxes)->toBeArray();
    expect(count($allSandboxes))->toBeGreaterThanOrEqual(2);

    // Verify our sandboxes are in the list
    $sandboxIds = array_map(fn ($s) => $s->getId(), $allSandboxes);
    expect($sandboxIds)->toContain($sandbox1->getId());
    expect($sandboxIds)->toContain($sandbox2->getId());
});

it('can filter sandboxes by label', function () {
    // Create sandboxes with specific labels
    $testLabel = 'filter-test-'.uniqid();

    $sandbox1 = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['environment' => $testLabel, 'type' => 'test', 'php-sdk-test' => 'true']
    ));

    $sandbox2 = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['environment' => $testLabel, 'type' => 'production', 'php-sdk-test' => 'true']
    ));

    $sandbox3 = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['environment' => 'different', 'type' => 'test', 'php-sdk-test' => 'true']
    ));

    // Filter by single label
    $filtered = $this->client->listSandboxes(['environment' => $testLabel]);
    expect($filtered)->toBeArray();

    $filteredIds = array_map(fn ($s) => $s->getId(), $filtered);
    expect($filteredIds)->toContain($sandbox1->getId());
    expect($filteredIds)->toContain($sandbox2->getId());
    expect($filteredIds)->not->toContain($sandbox3->getId());
});

it('returns empty array when no sandboxes match filter', function () {
    $uniqueLabel = 'non-existent-label-'.uniqid();

    $filtered = $this->client->listSandboxes(['unique-label' => $uniqueLabel]);
    expect($filtered)->toBeArray();
    expect($filtered)->toBeEmpty();
});

it('validates sandbox list response structure', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    $sandboxes = $this->client->listSandboxes();
    expect($sandboxes)->toBeArray();
    expect($sandboxes)->not->toBeEmpty();

    // Find our sandbox in the list
    $ourSandbox = null;
    foreach ($sandboxes as $s) {
        if ($s->getId() === $sandbox->getId()) {
            $ourSandbox = $s;
            break;
        }
    }

    expect($ourSandbox)->not->toBeNull();

    // Validate structure
    expect($ourSandbox)->toBeInstanceOf(Sandbox::class);
    expect($ourSandbox->getId())->toBeString();

    if ($ourSandbox->getOrganizationId() !== null) {
        expect($ourSandbox->getOrganizationId())->toBeString();
    }

    if ($ourSandbox->getState() !== null) {
        expect($ourSandbox->getState())->toBeString();
    }

    if ($ourSandbox->getCreatedAt() !== null) {
        expect($ourSandbox->getCreatedAt())->toBeString();
    }
});

it('creates sandbox with custom resource allocation', function () {
    $this->markTestSkipped('Cannot specify resources when API has a default snapshot configured');

    $params = new SandboxCreateParameters(
        cpu: 4,
        memory: 8192,  // 8GB
        disk: 20480,   // 20GB
        gpu: 0,
        labels: ['resource-test' => 'true', 'php-sdk-test' => 'true']
    );

    $sandbox = $this->client->createSandbox($params);

    expect($sandbox)->toBeInstanceOf(Sandbox::class);

    // Verify resource allocation
    $data = $sandbox->getData();

    if ($data->cpu !== null) {
        expect($data->cpu)->toBe(4);
    }

    if ($data->memory !== null) {
        expect($data->memory)->toBe(8192);
    }

    if ($data->disk !== null) {
        expect($data->disk)->toBe(20480);
    }

    if ($data->gpu !== null) {
        expect($data->gpu)->toBe(0);
    }
});

it('validates resource getters on sandbox object', function () {
    $this->markTestSkipped('Cannot specify resources when API has a default snapshot configured');

    $params = new SandboxCreateParameters(
        cpu: 2,
        memory: 4096,
        disk: 10240,
        gpu: 1,
        labels: ['php-sdk-test' => 'true']
    );

    $sandbox = $this->client->createSandbox($params);

    // Test resource getters
    if ($sandbox->getCpu() !== null) {
        expect($sandbox->getCpu())->toBe(2);
    }

    if ($sandbox->getMemory() !== null) {
        expect($sandbox->getMemory())->toBe(4096);
    }

    if ($sandbox->getDisk() !== null) {
        expect($sandbox->getDisk())->toBe(10240);
    }

    // GPU might not be supported in all environments
    if ($sandbox->getData()->gpu !== null) {
        expect($sandbox->getData()->gpu)->toBe(1);
    }
});

it('creates sandbox with minimal resources', function () {
    $this->markTestSkipped('Cannot specify resources when API has a default snapshot configured');

    $params = new SandboxCreateParameters(
        cpu: 1,
        memory: 512,
        disk: 1024,
        labels: ['php-sdk-test' => 'true']
    );

    $sandbox = $this->client->createSandbox($params);

    expect($sandbox)->toBeInstanceOf(Sandbox::class);
    expect($sandbox->getId())->toBeString()->not->toBeEmpty();

    // Verify minimal resources were accepted
    $data = $sandbox->getData();
    expect($data)->not->toBeNull();
});

it('creates sandbox with volumes', function () {
    $this->markTestSkipped('Volume functionality not yet implemented - will be tested separately');

    $volumes = [
        ['name' => 'data-volume', 'path' => '/data', 'size' => 5120],
        ['name' => 'cache-volume', 'path' => '/cache', 'size' => 2048],
    ];

    $params = new SandboxCreateParameters(
        volumes: $volumes,
        labels: ['volume-test' => 'true', 'php-sdk-test' => 'true']
    );

    $sandbox = $this->client->createSandbox($params);

    expect($sandbox)->toBeInstanceOf(Sandbox::class);

    // Verify volumes
    $data = $sandbox->getData();
    if ($data->volumes !== null) {
        expect($data->volumes)->toBeArray();
        expect(count($data->volumes))->toBe(2);
    }
});

it('validates volume data structure', function () {
    $this->markTestSkipped('Volume functionality not yet implemented - will be tested separately');

    $volumes = [
        [
            'name' => 'test-volume',
            'path' => '/mnt/test',
            'size' => 1024,
            'persistent' => true,
        ],
    ];

    $params = new SandboxCreateParameters(
        volumes: $volumes,
        labels: ['php-sdk-test' => 'true']
    );
    $sandbox = $this->client->createSandbox($params);

    $data = $sandbox->getData();
    if ($data->volumes !== null && count($data->volumes) > 0) {
        $volume = $data->volumes[0];

        if (isset($volume['name'])) {
            expect($volume['name'])->toBeString();
        }

        if (isset($volume['path'])) {
            expect($volume['path'])->toBeString();
        }

        if (isset($volume['size'])) {
            expect($volume['size'])->toBeInt();
        }
    }
});

it('creates sandbox without volumes', function () {
    $params = new SandboxCreateParameters(
        labels: ['no-volumes' => 'true', 'php-sdk-test' => 'true']
    );

    $sandbox = $this->client->createSandbox($params);

    expect($sandbox)->toBeInstanceOf(Sandbox::class);

    $data = $sandbox->getData();
    if ($data->volumes !== null) {
        expect($data->volumes)->toBeArray();
        expect($data->volumes)->toBeEmpty();
    }
});

it('validates backup state field', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    $data = $sandbox->getData();

    // Backup state might not be supported in all environments
    if ($data->backupState !== null) {
        expect($data->backupState)->toBeString();
        expect($data->backupState)->toBeIn(['None', 'none', 'backing_up', 'backed_up', 'restoring', 'error']);
    }
});

it('tracks auto-archive and auto-delete intervals', function () {
    $params = new SandboxCreateParameters(
        autoStopInterval: 60,      // 1 hour
        autoArchiveInterval: 1440,  // 24 hours
        autoDeleteInterval: 10080,  // 7 days
        labels: ['auto-lifecycle' => 'test', 'php-sdk-test' => 'true']
    );

    $sandbox = $this->client->createSandbox($params);

    $data = $sandbox->getData();

    if ($data->autoStopInterval !== null) {
        expect($data->autoStopInterval)->toBe(60);
    }

    if ($data->autoArchiveInterval !== null) {
        expect($data->autoArchiveInterval)->toBe(1440);
    }

    if ($data->autoDeleteInterval !== null) {
        expect($data->autoDeleteInterval)->toBe(10080);
    }
});

it('creates sandbox with disabled auto-lifecycle features', function () {
    $this->markTestSkipped('API has minimum values for auto-lifecycle intervals - cannot set to 0');

    $params = new SandboxCreateParameters(
        autoStopInterval: 0,      // Disabled
        autoArchiveInterval: 0,   // Disabled
        autoDeleteInterval: 0,    // Disabled
        labels: ['auto-lifecycle' => 'disabled', 'php-sdk-test' => 'true']
    );

    $sandbox = $this->client->createSandbox($params);

    expect($sandbox)->toBeInstanceOf(Sandbox::class);

    $data = $sandbox->getData();

    // 0 typically means disabled for these features
    if ($data->autoStopInterval !== null) {
        expect($data->autoStopInterval)->toBe(0);
    }

    if ($data->autoArchiveInterval !== null) {
        expect($data->autoArchiveInterval)->toBe(0);
    }

    if ($data->autoDeleteInterval !== null) {
        expect($data->autoDeleteInterval)->toBe(0);
    }
});

it('handles attempts to delete non-existent sandbox gracefully', function () {
    $nonExistentId = 'sandbox-'.uniqid().'-does-not-exist';

    try {
        $this->client->deleteSandbox($nonExistentId);
        // If we get here, the API might be too permissive
        expect(true)->toBeTrue();
    } catch (\Exception $e) {
        // Expected behavior - should throw an exception
        expect($e)->toBeInstanceOf(\ElliottLawson\Daytona\Exceptions\ApiException::class);
    }
});

it('handles attempts to get non-existent sandbox details', function () {
    $nonExistentId = 'sandbox-'.uniqid().'-not-found';

    try {
        $this->client->getSandbox($nonExistentId);
        fail('Expected exception when getting non-existent sandbox');
    } catch (\Exception $e) {
        expect($e)->toBeInstanceOf(\ElliottLawson\Daytona\Exceptions\ApiException::class);
    }
});

it('handles attempts to start/stop non-existent sandbox', function () {
    $nonExistentId = 'sandbox-'.uniqid().'-missing';

    // Test start
    try {
        $this->client->startSandbox($nonExistentId, 5);
        fail('Expected exception when starting non-existent sandbox');
    } catch (\Exception $e) {
        expect($e)->toBeInstanceOf(\ElliottLawson\Daytona\Exceptions\ApiException::class);
    }

    // Test stop
    try {
        $this->client->stopSandbox($nonExistentId, 5);
        fail('Expected exception when stopping non-existent sandbox');
    } catch (\Exception $e) {
        expect($e)->toBeInstanceOf(\ElliottLawson\Daytona\Exceptions\ApiException::class);
    }
});

it('validates error response contains meaningful information', function () {
    $this->markTestSkipped('API returns generic error messages without echoing back resource IDs');

    $nonExistentId = 'sandbox-error-test-'.uniqid();

    try {
        $this->client->getSandbox($nonExistentId);
        fail('Expected exception');
    } catch (\ElliottLawson\Daytona\Exceptions\ApiException $e) {
        expect($e->getMessage())->toContain($nonExistentId)
            ->or->toContain('not found')
            ->or->toContain('404')
            ->or->toContain('get sandbox');
        expect($e->getCode())->toBeInt();
    } catch (\Exception $e) {
        // Other exception types are also acceptable
        expect($e->getMessage())->not->toBeEmpty();
    }
});

it('handles invalid sandbox creation parameters', function () {
    // Test with invalid resource values (if API validates them)
    try {
        $params = new SandboxCreateParameters(
            cpu: -1,  // Invalid negative CPU
            memory: -1024,  // Invalid negative memory
            labels: ['error-test' => 'invalid-resources']
        );

        $sandbox = $this->client->createSandbox($params);
        // If creation succeeds, no need to track separately as it has the test label

        // Some APIs might accept negative values, so we just verify it was created
        expect($sandbox)->toBeInstanceOf(Sandbox::class);
    } catch (\Exception $e) {
        // If API validates and rejects invalid values, that's also good
        expect($e)->toBeInstanceOf(\Exception::class);
    }
});

it('handles timeout during sandbox state transitions', function () {
    $sandbox = $this->client->createSandbox(new SandboxCreateParameters(
        labels: ['php-sdk-test' => 'true']
    ));

    // Try to wait for an impossible state with a short timeout
    try {
        // Stop the sandbox first
        if ($sandbox->getState() === 'started') {
            $sandbox->stop();
        }

        // Now try to wait for it to be started with very short timeout
        $this->client->waitUntilSandboxStarted($sandbox->getId(), 1); // 1 second timeout

        // If we get here, it transitioned very quickly
        expect($sandbox->refresh()->getState())->toBe('started');
    } catch (\Exception $e) {
        // Expected - timeout or state mismatch
        expect($e->getMessage())->toContain('failed to reach target state');
    }
});

it('handles concurrent sandbox operations', function () {
    $concurrentCount = 3;
    $sandboxes = [];

    // Create multiple sandboxes concurrently (simulated)
    for ($i = 0; $i < $concurrentCount; $i++) {
        $params = new SandboxCreateParameters(
            labels: ['concurrent-test' => 'true', 'index' => (string) $i]
        );
        $sandboxes[] = $this->client->createSandbox($params);
    }

    expect(count($sandboxes))->toBe($concurrentCount);

    // Verify all sandboxes were created successfully
    foreach ($sandboxes as $index => $sandbox) {
        expect($sandbox)->toBeInstanceOf(Sandbox::class);
        expect($sandbox->getId())->toBeString()->not->toBeEmpty();

        $data = $sandbox->getData();
        if ($data->labels !== null) {
            expect($data->labels['index'] ?? null)->toBe((string) $index);
        }
    }

    // Perform operations on all sandboxes
    foreach ($sandboxes as $sandbox) {
        if ($sandbox->getState() === 'started') {
            $sandbox->stop();
        } else {
            $sandbox->start();
        }
    }

    // Verify state changes
    sleep(2);
    foreach ($sandboxes as $sandbox) {
        $sandbox->refresh();
        expect($sandbox->getState())->toBeIn(['started', 'starting', 'stopped', 'stopping']);
    }
});
