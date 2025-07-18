<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\FileInfo;
use ElliottLawson\Daytona\DTOs\FilePermissionsParams;
use ElliottLawson\Daytona\DTOs\Match;
use ElliottLawson\Daytona\DTOs\ReplaceResult;
use ElliottLawson\Daytona\DTOs\SearchFilesResponse;
use ElliottLawson\Daytona\Sandbox;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $config = new Config(
        apiKey: 'test-api-key',
        apiUrl: 'https://api.example.com',
        organizationId: 'test-org'
    );
    $client = new DaytonaClient($config);
    $this->sandbox = new Sandbox('sandbox-123', $client);
});

describe('createFolder convenience method', function () {
    it('can create directories with default permissions', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response([], 200),
        ]);

        $result = $this->sandbox->createFolder('/app/data');

        expect($result)->toBe($this->sandbox);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'toolbox/sandbox-123/toolbox/files/folder') &&
                   $request['path'] === '/app/data' &&
                   $request['mode'] === '755'; // default
        });
    });

    it('can create directories with custom permissions', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response([], 200),
        ]);

        $result = $this->sandbox->createFolder('/app/uploads', '777');

        expect($result)->toBe($this->sandbox);

        Http::assertSent(function ($request) {
            return $request['path'] === '/app/uploads' &&
                   $request['mode'] === '777';
        });
    });

    it('supports method chaining', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response([], 200),
        ]);

        $result = $this->sandbox
            ->createFolder('/app/logs', '755')
            ->createFolder('/app/cache', '755')
            ->createFolder('/app/temp', '777');

        expect($result)->toBe($this->sandbox);

        Http::assertSentCount(3);
    });
});

describe('moveFile convenience method', function () {
    it('can move files and directories', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/move*' => Http::response([], 200),
        ]);

        $result = $this->sandbox->moveFile('/tmp/file.txt', '/app/file.txt');

        expect($result)->toBe($this->sandbox);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'toolbox/sandbox-123/toolbox/files/move') &&
                   $request['source'] === '/tmp/file.txt' &&
                   $request['destination'] === '/app/file.txt';
        });
    });

    it('supports method chaining for multiple moves', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/move*' => Http::response([], 200),
        ]);

        $result = $this->sandbox
            ->moveFile('/tmp/file1.txt', '/app/file1.txt')
            ->moveFile('/tmp/file2.txt', '/app/file2.txt');

        expect($result)->toBe($this->sandbox);

        Http::assertSentCount(2);
    });
});

describe('getFileDetails convenience method', function () {
    it('can get detailed file information', function () {
        $mockResponse = [
            'name' => 'config.json',
            'isDir' => false,
            'size' => 1024,
            'modTime' => '2024-01-15T10:30:00Z',
            'mode' => '644',
            'permissions' => 'rw-r--r--',
            'owner' => 'www-data',
            'group' => 'www-data'
        ];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/info*' => Http::response($mockResponse, 200),
        ]);

        $fileInfo = $this->sandbox->getFileDetails('/app/config.json');

        expect($fileInfo)->toBeInstanceOf(FileInfo::class)
            ->and($fileInfo->name)->toBe('config.json')
            ->and($fileInfo->mode)->toBe('644')
            ->and($fileInfo->owner)->toBe('www-data');
    });
});

describe('setFilePermissions convenience method', function () {
    it('can set permissions using FilePermissionsParams', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
        ]);

        $permissions = new FilePermissionsParams(
            mode: '644',
            owner: 'www-data',
            group: 'www-data'
        );

        $result = $this->sandbox->setFilePermissions('/app/config.php', $permissions);

        expect($result)->toBe($this->sandbox);

        Http::assertSent(function ($request) {
            return $request['path'] === '/app/config.php' &&
                   $request['mode'] === '644' &&
                   $request['owner'] === 'www-data' &&
                   $request['group'] === 'www-data';
        });
    });

    it('supports method chaining', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
        ]);

        $permissions = new FilePermissionsParams(mode: '755');

        $result = $this->sandbox
            ->setFilePermissions('/app/script1.sh', $permissions)
            ->setFilePermissions('/app/script2.sh', $permissions);

        expect($result)->toBe($this->sandbox);

        Http::assertSentCount(2);
    });
});

describe('setPermissions convenience method', function () {
    it('can set permissions with individual parameters', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
        ]);

        $result = $this->sandbox->setPermissions('/app/script.sh', '755');

        expect($result)->toBe($this->sandbox);

        Http::assertSent(function ($request) {
            return $request['path'] === '/app/script.sh' &&
                   $request['mode'] === '755' &&
                   !isset($request['owner']) &&
                   !isset($request['group']);
        });
    });

    it('can set permissions with all parameters', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
        ]);

        $result = $this->sandbox->setPermissions('/app/file.txt', '644', 'www-data', 'www-data');

        expect($result)->toBe($this->sandbox);

        Http::assertSent(function ($request) {
            return $request['path'] === '/app/file.txt' &&
                   $request['mode'] === '644' &&
                   $request['owner'] === 'www-data' &&
                   $request['group'] === 'www-data';
        });
    });

    it('can set only owner and group', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
        ]);

        $result = $this->sandbox->setPermissions('/app/data/', null, 'user', 'users');

        expect($result)->toBe($this->sandbox);

        Http::assertSent(function ($request) {
            return $request['path'] === '/app/data/' &&
                   !isset($request['mode']) &&
                   $request['owner'] === 'user' &&
                   $request['group'] === 'users';
        });
    });

    it('supports method chaining', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
        ]);

        $result = $this->sandbox
            ->setPermissions('/app/config.php', '600', 'www-data', 'www-data')
            ->setPermissions('/app/script.sh', '755');

        expect($result)->toBe($this->sandbox);

        Http::assertSentCount(2);
    });
});

describe('searchFiles convenience method', function () {
    it('can search for files by pattern', function () {
        $mockResponse = [
            'files' => ['/app/config.json', '/app/settings.json']
        ];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/search*' => Http::response($mockResponse, 200),
        ]);

        $result = $this->sandbox->searchFiles('/app', '*.json');

        expect($result)->toBeInstanceOf(SearchFilesResponse::class)
            ->and($result->files)->toBe(['/app/config.json', '/app/settings.json'])
            ->and($result->getCount())->toBe(2);
    });

    it('handles empty search results', function () {
        $mockResponse = ['files' => []];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/search*' => Http::response($mockResponse, 200),
        ]);

        $result = $this->sandbox->searchFiles('/app', '*.nonexistent');

        expect($result->isEmpty())->toBeTrue();
    });
});

describe('findInFiles convenience method', function () {
    it('can find text patterns in files', function () {
        $mockResponse = [
            [
                'file' => '/app/src/Controller.php',
                'line' => 42,
                'content' => '// TODO: Implement better error handling'
            ],
            [
                'file' => '/app/models/User.php',
                'line' => 15,
                'content' => 'public function getName(): string // FIXME: Add validation'
            ]
        ];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/find*' => Http::response($mockResponse, 200),
        ]);

        $matches = $this->sandbox->findInFiles('/app/src', 'TODO|FIXME');

        expect($matches)->toHaveCount(2)
            ->and($matches[0])->toBeInstanceOf(Match::class)
            ->and($matches[0]->file)->toBe('/app/src/Controller.php')
            ->and($matches[0]->line)->toBe(42)
            ->and($matches[1]->file)->toBe('/app/models/User.php');
    });

    it('handles no matches found', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/find*' => Http::response([], 200),
        ]);

        $matches = $this->sandbox->findInFiles('/app', 'nonexistent-pattern');

        expect($matches)->toBeArray()
            ->and($matches)->toHaveCount(0);
    });
});

describe('replaceInFiles convenience method', function () {
    it('can replace text in multiple files', function () {
        $mockResponse = [
            [
                'file' => '/app/config.php',
                'success' => true
            ],
            [
                'file' => '/app/settings.php',
                'success' => true
            ]
        ];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response($mockResponse, 200),
        ]);

        $files = ['/app/config.php', '/app/settings.php'];
        $results = $this->sandbox->replaceInFiles($files, 'debug = false', 'debug = true');

        expect($results)->toHaveCount(2)
            ->and($results[0])->toBeInstanceOf(ReplaceResult::class)
            ->and($results[0]->file)->toBe('/app/config.php')
            ->and($results[0]->isSuccess())->toBeTrue()
            ->and($results[1]->file)->toBe('/app/settings.php')
            ->and($results[1]->isSuccess())->toBeTrue();
    });

    it('handles mixed success and failure results', function () {
        $mockResponse = [
            [
                'file' => '/app/config.php',
                'success' => true
            ],
            [
                'file' => '/app/readonly.txt',
                'success' => false,
                'error' => 'Permission denied'
            ]
        ];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response($mockResponse, 200),
        ]);

        $files = ['/app/config.php', '/app/readonly.txt'];
        $results = $this->sandbox->replaceInFiles($files, 'old', 'new');

        expect($results)->toHaveCount(2)
            ->and($results[0]->isSuccess())->toBeTrue()
            ->and($results[1]->isSuccess())->toBeFalse()
            ->and($results[1]->error)->toBe('Permission denied');
    });
});

describe('replaceInFile convenience method', function () {
    it('can replace text in a single file successfully', function () {
        $mockResponse = [
            [
                'file' => '/app/config.php',
                'success' => true
            ]
        ];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response($mockResponse, 200),
        ]);

        $result = $this->sandbox->replaceInFile('/app/config.php', 'debug = false', 'debug = true');

        expect($result)->toBeInstanceOf(ReplaceResult::class)
            ->and($result->file)->toBe('/app/config.php')
            ->and($result->isSuccess())->toBeTrue();

        Http::assertSent(function ($request) {
            $requestData = $request->data();
            return $requestData['files'] === ['/app/config.php'] &&
                   $requestData['pattern'] === 'debug = false' &&
                   $requestData['newValue'] === 'debug = true';
        });
    });

    it('handles file replacement failure', function () {
        $mockResponse = [
            [
                'file' => '/app/readonly.txt',
                'success' => false,
                'error' => 'Permission denied'
            ]
        ];

        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response($mockResponse, 200),
        ]);

        $result = $this->sandbox->replaceInFile('/app/readonly.txt', 'old', 'new');

        expect($result)->toBeInstanceOf(ReplaceResult::class)
            ->and($result->file)->toBe('/app/readonly.txt')
            ->and($result->isSuccess())->toBeFalse()
            ->and($result->error)->toBe('Permission denied');
    });

    it('handles no result returned from API', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/replace*' => Http::response([], 200),
        ]);

        $result = $this->sandbox->replaceInFile('/app/file.txt', 'pattern', 'replacement');

        expect($result)->toBeInstanceOf(ReplaceResult::class)
            ->and($result->file)->toBe('/app/file.txt')
            ->and($result->isSuccess())->toBeFalse()
            ->and($result->error)->toBe('No result returned');
    });
});

describe('method chaining workflow', function () {
    it('can chain multiple file operations together', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response([], 200),
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
            '*/toolbox/sandbox-123/toolbox/files/move*' => Http::response([], 200),
        ]);

        $result = $this->sandbox
            ->createFolder('/app/data', '755')
            ->createFolder('/app/logs', '755')
            ->setPermissions('/app/script.sh', '755')
            ->moveFile('/tmp/upload.txt', '/app/data/upload.txt');

        expect($result)->toBe($this->sandbox);

        Http::assertSentCount(4);
    });

    it('can combine file operations with existing operations', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response([], 200),
            '*/toolbox/sandbox-123/toolbox/files/upload*' => Http::response(['success' => true], 200),
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
        ]);

        $result = $this->sandbox
            ->createFolder('/app/uploads', '777')
            ->writeFile('/app/config.php', '<?php return [];')
            ->setPermissions('/app/config.php', '644', 'www-data', 'www-data');

        expect($result)->toBe($this->sandbox);

        Http::assertSentCount(3);
    });
});

describe('integration with existing sandbox methods', function () {
    it('new methods work alongside existing file operations', function () {
        Http::fake([
            '*/toolbox/sandbox-123/toolbox/files*' => Http::response([
                'files' => [
                    ['name' => 'config.php', 'path' => '/app/config.php', 'isDirectory' => false, 'size' => 1024],
                    ['name' => 'uploads', 'path' => '/app/uploads', 'isDirectory' => true, 'size' => 0],
                ]
            ], 200),
            '*/toolbox/sandbox-123/toolbox/files/folder*' => Http::response([], 200),
            '*/toolbox/sandbox-123/toolbox/files/permissions*' => Http::response([], 200),
        ]);

        // Use existing method
        $listing = $this->sandbox->listDirectory('/app');

        // Use new methods
        $this->sandbox
            ->createFolder('/app/new-folder', '755')
            ->setPermissions('/app/new-folder', '755', 'www-data', 'www-data');

        expect($listing->files)->toHaveCount(2);

        Http::assertSentCount(3);
    });
});