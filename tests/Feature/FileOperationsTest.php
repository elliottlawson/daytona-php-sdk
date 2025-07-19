<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\FileInfo;
use ElliottLawson\Daytona\DTOs\FilePermissionsParams;
use ElliottLawson\Daytona\DTOs\ReplaceResult;
use ElliottLawson\Daytona\DTOs\SearchFilesResponse;
use ElliottLawson\Daytona\DTOs\SearchMatch;
use ElliottLawson\Daytona\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->config = new Config(
        apiKey: 'test-api-key',
        apiUrl: 'https://api.example.com',
        organizationId: 'test-org'
    );
    $this->client = new DaytonaClient($this->config);
    $this->sandboxId = 'sandbox-123';
});

describe('createFolder method', function () {
    it('can create a directory with permissions', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response([], 200),
        ]);

        $this->client->createFolder($this->sandboxId, '/app/data', '755');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'toolbox/sandbox-123/toolbox/files/folder') &&
                   $request->method() === 'POST' &&
                   str_contains($request->url(), 'path='.urlencode('/app/data')) &&
                   $request->data()['mode'] === '755';
        });
    });

    it('throws exception on API error', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response(['error' => 'Permission denied'], 403),
        ]);

        expect(fn () => $this->client->createFolder($this->sandboxId, '/restricted', '755'))
            ->toThrow(ApiException::class, 'Access denied. Please check your permissions.');
    });
});

describe('moveFile method', function () {
    it('can move files and directories', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/move*' => Http::response([], 200),
        ]);

        $this->client->moveFile($this->sandboxId, '/tmp/file.txt', '/app/file.txt');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'toolbox/sandbox-123/toolbox/files/move') &&
                   $request->method() === 'POST' &&
                   str_contains($request->url(), 'source='.urlencode('/tmp/file.txt')) &&
                   str_contains($request->url(), 'destination='.urlencode('/app/file.txt'));
        });
    });

    it('throws exception on API error', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/move*' => Http::response(['error' => 'File not found'], 404),
        ]);

        expect(fn () => $this->client->moveFile($this->sandboxId, '/nonexistent.txt', '/app/file.txt'))
            ->toThrow(ApiException::class, 'Resource not found.');
    });
});

describe('getFileDetails method', function () {
    it('can get detailed file information', function () {
        $mockResponse = [
            'name' => 'config.json',
            'isDir' => false,
            'size' => 1024,
            'modTime' => '2024-01-15T10:30:00Z',
            'mode' => '644',
            'permissions' => 'rw-r--r--',
            'owner' => 'www-data',
            'group' => 'www-data',
        ];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/info*' => Http::response($mockResponse, 200),
        ]);

        $fileInfo = $this->client->getFileDetails($this->sandboxId, '/app/config.json');

        expect($fileInfo)->toBeInstanceOf(FileInfo::class)
            ->and($fileInfo->name)->toBe('config.json')
            ->and($fileInfo->isDirectory)->toBeFalse()
            ->and($fileInfo->size)->toBe(1024)
            ->and($fileInfo->mode)->toBe('644')
            ->and($fileInfo->owner)->toBe('www-data')
            ->and($fileInfo->group)->toBe('www-data');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'toolbox/sandbox-123/toolbox/files/info') &&
                   $request->method() === 'GET' &&
                   str_contains($request->url(), 'path=%2Fapp%2Fconfig.json');
        });
    });

    it('throws exception when file not found', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/info*' => Http::response(['error' => 'File not found'], 404),
        ]);

        expect(fn () => $this->client->getFileDetails($this->sandboxId, '/nonexistent.txt'))
            ->toThrow(ApiException::class, 'Resource not found.');
    });
});

describe('setFilePermissions method', function () {
    it('can set file permissions with all parameters', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
        ]);

        $permissions = new FilePermissionsParams(
            mode: '644',
            owner: 'www-data',
            group: 'www-data'
        );

        $this->client->setFilePermissions($this->sandboxId, '/app/config.php', $permissions);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'toolbox/sandbox-123/toolbox/files/permissions') &&
                   $request->method() === 'POST' &&
                   str_contains($request->url(), 'path='.urlencode('/app/config.php')) &&
                   str_contains($request->url(), 'mode=644') &&
                   str_contains($request->url(), 'owner=www-data') &&
                   str_contains($request->url(), 'group=www-data');
        });
    });

    it('can set file permissions with partial parameters', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
        ]);

        $permissions = new FilePermissionsParams(mode: '755');

        $this->client->setFilePermissions($this->sandboxId, '/app/script.sh', $permissions);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'toolbox/sandbox-123/toolbox/files/permissions') &&
                   str_contains($request->url(), 'path='.urlencode('/app/script.sh')) &&
                   str_contains($request->url(), 'mode=755');
        });
    });

    it('throws exception on permission denied', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response(['error' => 'Permission denied'], 403),
        ]);

        $permissions = new FilePermissionsParams(mode: '644');

        expect(fn () => $this->client->setFilePermissions($this->sandboxId, '/readonly.txt', $permissions))
            ->toThrow(ApiException::class, 'Access denied. Please check your permissions.');
    });
});

describe('searchFiles method', function () {
    it('can search for files by pattern', function () {
        $mockResponse = [
            'files' => ['/app/config.json', '/app/settings.json', '/app/package.json'],
        ];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/search*' => Http::response($mockResponse, 200),
        ]);

        $result = $this->client->searchFiles($this->sandboxId, '/app', '*.json');

        expect($result)->toBeInstanceOf(SearchFilesResponse::class)
            ->and($result->files)->toBe(['/app/config.json', '/app/settings.json', '/app/package.json'])
            ->and($result->getCount())->toBe(3);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'toolbox/sandbox-123/toolbox/files/search') &&
                   $request->method() === 'GET' &&
                   str_contains($request->url(), 'path=%2Fapp') &&
                   str_contains($request->url(), 'pattern=%2A.json');
        });
    });

    it('handles empty search results', function () {
        $mockResponse = ['files' => []];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/search*' => Http::response($mockResponse, 200),
        ]);

        $result = $this->client->searchFiles($this->sandboxId, '/empty', '*.nonexistent');

        expect($result->isEmpty())->toBeTrue()
            ->and($result->getCount())->toBe(0);
    });

    it('throws exception on API error', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/search*' => Http::response(['error' => 'Invalid pattern'], 400),
        ]);

        expect(fn () => $this->client->searchFiles($this->sandboxId, '/app', '[invalid'))
            ->toThrow(ApiException::class);
    });
});

describe('findInFiles method', function () {
    it('can find text patterns in files', function () {
        $mockResponse = [
            [
                'file' => '/app/src/Controller.php',
                'line' => 42,
                'content' => '// TODO: Implement better error handling',
            ],
            [
                'file' => '/app/models/User.php',
                'line' => 15,
                'content' => 'public function getName(): string // FIXME: Add validation',
            ],
        ];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/find*' => Http::response($mockResponse, 200),
        ]);

        $matches = $this->client->findInFiles($this->sandboxId, '/app/src', 'TODO|FIXME');

        expect($matches)->toHaveCount(2)
            ->and($matches[0])->toBeInstanceOf(SearchMatch::class)
            ->and($matches[0]->file)->toBe('/app/src/Controller.php')
            ->and($matches[0]->line)->toBe(42)
            ->and($matches[0]->content)->toBe('// TODO: Implement better error handling')
            ->and($matches[1]->file)->toBe('/app/models/User.php')
            ->and($matches[1]->line)->toBe(15);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'toolbox/sandbox-123/toolbox/files/find') &&
                   $request->method() === 'GET' &&
                   str_contains($request->url(), 'path=%2Fapp%2Fsrc') &&
                   str_contains($request->url(), 'pattern=TODO%7CFIXME');
        });
    });

    it('handles no matches found', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/find*' => Http::response([], 200),
        ]);

        $matches = $this->client->findInFiles($this->sandboxId, '/app', 'nonexistent-pattern');

        expect($matches)->toBeArray()
            ->and($matches)->toHaveCount(0);
    });

    it('throws exception on API error', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/find*' => Http::response(['error' => 'Invalid regex'], 400),
        ]);

        expect(fn () => $this->client->findInFiles($this->sandboxId, '/app', '[invalid-regex'))
            ->toThrow(ApiException::class);
    });
});

describe('replaceInFiles method', function () {
    it('can replace text in multiple files', function () {
        $mockResponse = [
            [
                'file' => '/app/config.php',
                'success' => true,
            ],
            [
                'file' => '/app/settings.php',
                'success' => true,
            ],
        ];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response($mockResponse, 200),
        ]);

        $files = ['/app/config.php', '/app/settings.php'];
        $results = $this->client->replaceInFiles($this->sandboxId, $files, 'debug = false', 'debug = true');

        expect($results)->toHaveCount(2)
            ->and($results[0])->toBeInstanceOf(ReplaceResult::class)
            ->and($results[0]->file)->toBe('/app/config.php')
            ->and($results[0]->isSuccess())->toBeTrue()
            ->and($results[1]->file)->toBe('/app/settings.php')
            ->and($results[1]->isSuccess())->toBeTrue();

        Http::assertSent(function ($request) {
            $requestData = $request->data();

            return str_contains($request->url(), 'toolbox/sandbox-123/toolbox/files/replace') &&
                   $request->method() === 'POST' &&
                   $requestData['files'] === ['/app/config.php', '/app/settings.php'] &&
                   $requestData['pattern'] === 'debug = false' &&
                   $requestData['newValue'] === 'debug = true';
        });
    });

    it('handles mixed success and failure results', function () {
        $mockResponse = [
            [
                'file' => '/app/config.php',
                'success' => true,
            ],
            [
                'file' => '/app/readonly.txt',
                'success' => false,
                'error' => 'Permission denied',
            ],
        ];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response($mockResponse, 200),
        ]);

        $files = ['/app/config.php', '/app/readonly.txt'];
        $results = $this->client->replaceInFiles($this->sandboxId, $files, 'old', 'new');

        expect($results)->toHaveCount(2)
            ->and($results[0]->isSuccess())->toBeTrue()
            ->and($results[1]->isSuccess())->toBeFalse()
            ->and($results[1]->hasError())->toBeTrue()
            ->and($results[1]->error)->toBe('Permission denied');
    });

    it('throws exception on API error', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response(['error' => 'Invalid request'], 400),
        ]);

        expect(fn () => $this->client->replaceInFiles($this->sandboxId, [], 'pattern', 'replacement'))
            ->toThrow(ApiException::class);
    });
});

describe('HTTP request headers and organization ID', function () {
    it('includes organization ID header when configured', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response([], 200),
        ]);

        $this->client->createFolder($this->sandboxId, '/test', '755');

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Daytona-Organization-ID', 'test-org') &&
                   $request->hasHeader('Authorization', 'Bearer test-api-key');
        });
    });

    it('includes proper authentication headers', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/info*' => Http::response([
                'name' => 'test.txt',
                'isDir' => false,
                'size' => 100,
                'modTime' => '2024-01-15T10:30:00Z',
                'mode' => '644',
                'permissions' => 'rw-r--r--',
                'owner' => 'user',
                'group' => 'users',
            ], 200),
        ]);

        $this->client->getFileDetails($this->sandboxId, '/test.txt');

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer test-api-key') &&
                   $request->hasHeader('Accept', 'application/json');
        });
    });
});
