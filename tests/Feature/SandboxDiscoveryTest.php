<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\PaginatedSandboxesResponse;
use ElliottLawson\Daytona\DTOs\SandboxFilter;
use ElliottLawson\Daytona\Exceptions\SandboxException;
use ElliottLawson\Daytona\Sandbox;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->config = new Config(
        apiKey: 'test-api-key',
        apiUrl: 'https://api.example.com',
        organizationId: 'test-org'
    );
    $this->client = new DaytonaClient($this->config);

    // Sample sandbox data for testing
    $this->sampleSandboxes = [
        [
            'id' => 'sandbox-1',
            'state' => 'started',
            'organizationId' => 'test-org',
            'user' => 'john',
            'env' => [],
            'labels' => ['environment' => 'dev', 'project' => 'frontend'],
            'public' => false,
            'target' => 'test-target',
            'cpu' => 2,
            'gpu' => 0,
            'memory' => 4,
            'disk' => 20,
        ],
        [
            'id' => 'sandbox-2',
            'state' => 'stopped',
            'organizationId' => 'test-org',
            'user' => 'jane',
            'env' => [],
            'labels' => ['environment' => 'prod', 'project' => 'backend'],
            'public' => true,
            'target' => 'test-target',
            'cpu' => 4,
            'gpu' => 0,
            'memory' => 8,
            'disk' => 40,
        ],
        [
            'id' => 'sandbox-3',
            'state' => 'started',
            'organizationId' => 'test-org',
            'user' => 'john',
            'env' => [],
            'labels' => ['environment' => 'dev', 'project' => 'backend'],
            'public' => false,
            'target' => 'test-target',
            'cpu' => 2,
            'gpu' => 0,
            'memory' => 4,
            'disk' => 20,
        ],
    ];
});

describe('Sandbox Discovery and Filtering', function () {
    describe('List All Sandboxes', function () {
        it('lists all sandboxes without filters', function () {
            Http::fake([
                '*/sandbox*' => Http::response($this->sampleSandboxes, 200),
            ]);

            $sandboxes = $this->client->listSandboxes();

            expect($sandboxes)->toHaveCount(3)
                ->and($sandboxes[0])->toBeInstanceOf(Sandbox::class)
                ->and($sandboxes[0]->getId())->toBe('sandbox-1')
                ->and($sandboxes[1]->getId())->toBe('sandbox-2')
                ->and($sandboxes[2]->getId())->toBe('sandbox-3');

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'sandbox') &&
                       $request->method() === 'GET' &&
                       empty($request->data());
            });
        });

        it('returns empty array when no sandboxes exist', function () {
            Http::fake([
                '*/sandbox*' => Http::response([], 200),
            ]);

            $sandboxes = $this->client->listSandboxes();

            expect($sandboxes)->toHaveCount(0);
        });

        it('handles the paginated items response shape from Daytona 0.180+', function () {
            Http::fake([
                '*/sandbox*' => Http::response([
                    'items' => $this->sampleSandboxes,
                    'nextCursor' => 'cursor-abc',
                ], 200),
            ]);

            $sandboxes = $this->client->listSandboxes();

            expect($sandboxes)->toHaveCount(3)
                ->and($sandboxes[0])->toBeInstanceOf(Sandbox::class)
                ->and($sandboxes[0]->getId())->toBe('sandbox-1')
                ->and($sandboxes[2]->getId())->toBe('sandbox-3');
        });

        it('returns an empty array for an empty paginated response', function () {
            Http::fake([
                '*/sandbox*' => Http::response([
                    'items' => [],
                    'nextCursor' => null,
                ], 200),
            ]);

            $sandboxes = $this->client->listSandboxes();

            expect($sandboxes)->toHaveCount(0);
        });
    });

    describe('Cursor Pagination (Daytona 0.180+)', function () {
        it('lists a page of sandboxes with cursor and next cursor', function () {
            Http::fake([
                '*/sandbox*' => Http::response([
                    'items' => $this->sampleSandboxes,
                    'nextCursor' => 'cursor-abc',
                ], 200),
            ]);

            $result = $this->client->listSandboxesPaginated();

            expect($result)->toBeInstanceOf(PaginatedSandboxesResponse::class)
                ->and($result->items)->toHaveCount(3)
                ->and($result->items[0])->toBeInstanceOf(Sandbox::class)
                ->and($result->items[0]->getId())->toBe('sandbox-1')
                ->and($result->nextCursor)->toBe('cursor-abc')
                ->and($result->hasMore())->toBeTrue();
        });

        it('exposes the cursor and limit as query parameters', function () {
            Http::fake([
                '*/sandbox*' => Http::response([
                    'items' => [$this->sampleSandboxes[0]],
                    'nextCursor' => 'cursor-def',
                ], 200),
            ]);

            $result = $this->client->listSandboxesPaginated(null, 'cursor-123', 10);

            expect($result->count())->toBe(1)
                ->and($result->nextCursor)->toBe('cursor-def');

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'sandbox') &&
                       str_contains($request->url(), 'cursor=cursor-123') &&
                       str_contains($request->url(), 'limit=10');
            });
        });

        it('accepts a SandboxFilter with cursor and limit', function () {
            Http::fake([
                '*/sandbox*' => Http::response([
                    'items' => $this->sampleSandboxes,
                    'nextCursor' => null,
                ], 200),
            ]);

            $filter = new SandboxFilter(cursor: 'filter-cursor', limit: 25);

            $result = $this->client->listSandboxesPaginated($filter);

            expect($result->nextCursor)->toBeNull()
                ->and($result->hasMore())->toBeFalse();

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'cursor=filter-cursor') &&
                       str_contains($request->url(), 'limit=25');
            });
        });

        it('returns an empty page when there are no more sandboxes', function () {
            Http::fake([
                '*/sandbox*' => Http::response([
                    'items' => [],
                    'nextCursor' => null,
                ], 200),
            ]);

            $result = $this->client->listSandboxesPaginated();

            expect($result->items)->toBe([])
                ->and($result->isEmpty())->toBeTrue()
                ->and($result->count())->toBe(0);
        });

        it('still supports the legacy plain-array response', function () {
            Http::fake([
                '*/sandbox*' => Http::response($this->sampleSandboxes, 200),
            ]);

            $result = $this->client->listSandboxesPaginated();

            expect($result->items)->toHaveCount(3)
                ->and($result->nextCursor)->toBeNull();
        });
    });

    describe('Filter by Labels (Legacy Array Syntax)', function () {
        it('filters sandboxes by labels using array syntax', function () {
            $filteredSandboxes = array_filter($this->sampleSandboxes, function ($sandbox) {
                return ($sandbox['labels']['environment'] ?? null) === 'dev';
            });

            Http::fake([
                '*/sandbox*' => Http::response(array_values($filteredSandboxes), 200),
            ]);

            $sandboxes = $this->client->listSandboxes(['environment' => 'dev']);

            expect($sandboxes)->toHaveCount(2)
                ->and($sandboxes[0]->getId())->toBe('sandbox-1')
                ->and($sandboxes[1]->getId())->toBe('sandbox-3');

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'sandbox') &&
                       $request->data()['labels'] === '{"environment":"dev"}';
            });
        });

        it('filters sandboxes by multiple labels', function () {
            $filteredSandboxes = array_filter($this->sampleSandboxes, function ($sandbox) {
                return ($sandbox['labels']['environment'] ?? null) === 'dev' &&
                       ($sandbox['labels']['project'] ?? null) === 'backend';
            });

            Http::fake([
                '*/sandbox*' => Http::response(array_values($filteredSandboxes), 200),
            ]);

            $sandboxes = $this->client->listSandboxes([
                'environment' => 'dev',
                'project' => 'backend',
            ]);

            expect($sandboxes)->toHaveCount(1)
                ->and($sandboxes[0]->getId())->toBe('sandbox-3');
        });
    });

    describe('SandboxFilter DTO', function () {
        it('creates filter by labels', function () {
            $filter = SandboxFilter::byLabels(['environment' => 'dev']);

            expect($filter->labels)->toBe(['environment' => 'dev'])
                ->and($filter->id)->toBeNull()
                ->and($filter->state)->toBeNull();
        });

        it('creates filter by ID', function () {
            $filter = SandboxFilter::byId('sandbox-123');

            expect($filter->id)->toBe('sandbox-123')
                ->and($filter->labels)->toBeNull();
        });

        it('creates filter by state', function () {
            $filter = SandboxFilter::byState('started');

            expect($filter->state)->toBe('started')
                ->and($filter->labels)->toBeNull();
        });

        it('creates filter by user', function () {
            $filter = SandboxFilter::byUser('john');

            expect($filter->user)->toBe('john')
                ->and($filter->labels)->toBeNull();
        });

        it('supports fluent interface for building complex filters', function () {
            $filter = SandboxFilter::byLabels(['environment' => 'dev'])
                ->withState('started')
                ->withLabels(['project' => 'backend']);

            expect($filter->labels)->toBe(['environment' => 'dev', 'project' => 'backend'])
                ->and($filter->state)->toBe('started');
        });

        it('converts filter to array for API requests', function () {
            $filter = SandboxFilter::byLabels(['env' => 'dev'])
                ->withState('started')
                ->withUser('john');

            $array = $filter->toArray();

            expect($array)->toBe([
                'labels' => '{"env":"dev"}',
                'state' => 'started',
                'user' => 'john',
            ]);
        });

        it('handles public flag in filter', function () {
            $publicFilter = new SandboxFilter(public: true);
            $privateFilter = new SandboxFilter(public: false);

            expect($publicFilter->toArray()['public'])->toBe('true')
                ->and($privateFilter->toArray()['public'])->toBe('false');
        });

        it('supports cursor and limit pagination fields', function () {
            $filter = (new SandboxFilter)
                ->withCursor('cursor-abc')
                ->withLimit(50);

            expect($filter->toArray())->toBe([
                'cursor' => 'cursor-abc',
                'limit' => 50,
            ]);
        });

        it('maps isPublic to the modern isPublic query param', function () {
            $publicFilter = new SandboxFilter(isPublic: true);
            $privateFilter = new SandboxFilter(isPublic: false);

            expect($publicFilter->toArray()['isPublic'])->toBe('true')
                ->and($privateFilter->toArray()['isPublic'])->toBe('false')
                ->and($publicFilter->toArray())->not->toHaveKey('public');
        });

        it('supports the modern states array filter', function () {
            $filter = SandboxFilter::byStates(['started', 'stopped']);

            expect($filter->toArray()['states'])->toBe(['started', 'stopped']);
        });

        it('combines modern fields into clean query params', function () {
            $filter = SandboxFilter::byName('my-sandbox')
                ->withPublic(false)
                ->withStates(['started'])
                ->withCursor('next-cursor')
                ->withLimit(10);

            expect($filter->toArray())->toBe([
                'name' => 'my-sandbox',
                'states' => ['started'],
                'isPublic' => 'false',
                'cursor' => 'next-cursor',
                'limit' => 10,
            ]);
        });
    });

    describe('Filter with SandboxFilter DTO', function () {
        it('filters sandboxes using SandboxFilter DTO', function () {
            $filteredSandboxes = array_filter($this->sampleSandboxes, function ($sandbox) {
                return $sandbox['state'] === 'started';
            });

            Http::fake([
                '*/sandbox*' => Http::response(array_values($filteredSandboxes), 200),
            ]);

            $filter = SandboxFilter::byState('started');
            $sandboxes = $this->client->listSandboxes($filter);

            expect($sandboxes)->toHaveCount(2);

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'sandbox') &&
                       str_contains($request->url(), 'state=started');
            });
        });

        it('filters sandboxes with complex SandboxFilter', function () {
            $filteredSandboxes = array_filter($this->sampleSandboxes, function ($sandbox) {
                return $sandbox['user'] === 'john' && $sandbox['state'] === 'started';
            });

            Http::fake([
                '*/sandbox*' => Http::response(array_values($filteredSandboxes), 200),
            ]);

            $filter = SandboxFilter::byUser('john')->withState('started');
            $sandboxes = $this->client->listSandboxes($filter);

            expect($sandboxes)->toHaveCount(2);

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'sandbox') &&
                       str_contains($request->url(), 'user=john') &&
                       str_contains($request->url(), 'state=started');
            });
        });

        it('sends modern states and isPublic filters as query params', function () {
            Http::fake([
                '*/sandbox*' => Http::response([
                    'items' => [],
                    'nextCursor' => null,
                ], 200),
            ]);

            $filter = SandboxFilter::byStates(['started', 'stopped'])->withPublic(false);
            $this->client->listSandboxes($filter);

            Http::assertSent(function ($request) {
                $data = $request->data();

                return ($data['states'] ?? null) === ['started', 'stopped'] &&
                       ($data['isPublic'] ?? null) === 'false';
            });
        });
    });

    describe('Find Specific Sandboxes', function () {
        it('finds sandbox by labels', function () {
            $targetSandbox = $this->sampleSandboxes[1]; // prod backend sandbox

            Http::fake([
                '*/sandbox*' => Http::response([$targetSandbox], 200),
            ]);

            $sandbox = $this->client->findSandboxByLabels([
                'environment' => 'prod',
                'project' => 'backend',
            ]);

            expect($sandbox)->toBeInstanceOf(Sandbox::class)
                ->and($sandbox->getId())->toBe('sandbox-2');
        });

        it('throws exception when no sandbox found by labels', function () {
            Http::fake([
                '*/sandbox*' => Http::response([], 200),
            ]);

            $this->client->findSandboxByLabels(['nonexistent' => 'label']);
        })->throws(SandboxException::class, 'with labels');

        it('finds sandbox using SandboxFilter', function () {
            $targetSandbox = $this->sampleSandboxes[0]; // john's frontend dev sandbox

            Http::fake([
                '*/sandbox*' => Http::response([$targetSandbox], 200),
            ]);

            $filter = SandboxFilter::byUser('john')
                ->withLabels(['project' => 'frontend']);

            $sandbox = $this->client->findSandbox($filter);

            expect($sandbox)->toBeInstanceOf(Sandbox::class)
                ->and($sandbox->getId())->toBe('sandbox-1');
        });

        it('throws exception when no sandbox found with filter', function () {
            Http::fake([
                '*/sandbox*' => Http::response([], 200),
            ]);

            $filter = SandboxFilter::byUser('nonexistent');

            $this->client->findSandbox($filter);
        })->throws(SandboxException::class, 'matching filter criteria');

        it('returns first sandbox when multiple matches exist', function () {
            $multipleSandboxes = [
                $this->sampleSandboxes[0],
                $this->sampleSandboxes[2],
            ]; // Both john's sandboxes

            Http::fake([
                '*/sandbox*' => Http::response($multipleSandboxes, 200),
            ]);

            $sandbox = $this->client->findSandboxByLabels(['environment' => 'dev']);

            expect($sandbox->getId())->toBe('sandbox-1'); // First one returned
        });
    });

    describe('Error Handling', function () {
        it('handles API errors gracefully during listing', function () {
            Http::fake([
                '*/sandbox*' => Http::response(['error' => 'Unauthorized'], 401),
            ]);

            $this->client->listSandboxes();
        })->throws(Exception::class);

        it('handles empty labels filter correctly', function () {
            Http::fake([
                '*/sandbox*' => Http::response($this->sampleSandboxes, 200),
            ]);

            $sandboxes = $this->client->listSandboxes([]);

            expect($sandboxes)->toHaveCount(3);

            // Should not include labels parameter in request
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'sandbox') &&
                       ! isset($request->data()['labels']);
            });
        });
    });

    describe('Integration with Existing Features', function () {
        it('can chain discovery with waiting operations', function () {
            $sandboxData = $this->sampleSandboxes[0];
            $sandboxData['state'] = 'started';

            Http::fake([
                '*/sandbox?*' => Http::response([$sandboxData], 200), // For listSandboxes with filter
                '*/sandbox/sandbox-1' => Http::response($sandboxData, 200), // For getSandbox calls
                '*/sandbox/sandbox-1/start' => Http::response($sandboxData, 200),
                '*/toolbox/sandbox-1/toolbox/process/execute' => Http::response([
                    'exitCode' => 0,
                    'stdout' => 'Hello World',
                    'stderr' => '',
                ], 200),
            ]);

            // Find sandbox and chain operations
            $sandbox = $this->client->findSandboxByLabels(['environment' => 'dev']);
            $result = $sandbox->start()->exec('echo "Hello World"');

            expect($result->output)->toBe('Hello World')
                ->and($result->exitCode)->toBe(0);
        });
    });
});
