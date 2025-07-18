<?php

use ElliottLawson\Daytona\DTOs\FileInfo;
use ElliottLawson\Daytona\DTOs\FilePermissionsParams;
use ElliottLawson\Daytona\DTOs\ReplaceRequest;
use ElliottLawson\Daytona\DTOs\ReplaceResult;
use ElliottLawson\Daytona\DTOs\SearchFilesResponse;
use ElliottLawson\Daytona\DTOs\SearchMatch;

describe('Enhanced FileInfo DTO', function () {
    it('can be created with all required fields', function () {
        $fileInfo = new FileInfo(
            name: 'config.json',
            isDirectory: false,
            size: 1024,
            modifiedAt: '2024-01-15T10:30:00Z',
            mode: '644',
            permissions: 'rw-r--r--',
            owner: 'www-data',
            group: 'www-data'
        );

        expect($fileInfo->name)->toBe('config.json')
            ->and($fileInfo->isDirectory)->toBeFalse()
            ->and($fileInfo->size)->toBe(1024)
            ->and($fileInfo->modifiedAt)->toBe('2024-01-15T10:30:00Z')
            ->and($fileInfo->mode)->toBe('644')
            ->and($fileInfo->permissions)->toBe('rw-r--r--')
            ->and($fileInfo->owner)->toBe('www-data')
            ->and($fileInfo->group)->toBe('www-data')
            ->and($fileInfo->path)->toBeNull();
    });

    it('can be created with optional path for backward compatibility', function () {
        $fileInfo = new FileInfo(
            name: 'script.sh',
            isDirectory: false,
            size: 512,
            modifiedAt: '2024-01-15T10:30:00Z',
            mode: '755',
            permissions: 'rwxr-xr-x',
            owner: 'user',
            group: 'users',
            path: '/app/scripts/script.sh'
        );

        expect($fileInfo->path)->toBe('/app/scripts/script.sh')
            ->and($fileInfo->getPath())->toBe('/app/scripts/script.sh');
    });

    it('can be serialized from API response data', function () {
        $apiData = [
            'name' => 'readme.md',
            'isDir' => false,
            'size' => 2048,
            'modTime' => '2024-01-15T10:30:00Z',
            'mode' => '644',
            'permissions' => 'rw-r--r--',
            'owner' => 'developer',
            'group' => 'staff',
        ];

        $fileInfo = FileInfo::fromArray($apiData);

        expect($fileInfo->name)->toBe('readme.md')
            ->and($fileInfo->isDirectory)->toBeFalse()
            ->and($fileInfo->size)->toBe(2048)
            ->and($fileInfo->modifiedAt)->toBe('2024-01-15T10:30:00Z')
            ->and($fileInfo->mode)->toBe('644')
            ->and($fileInfo->permissions)->toBe('rw-r--r--')
            ->and($fileInfo->owner)->toBe('developer')
            ->and($fileInfo->group)->toBe('staff');
    });

    it('handles legacy field names for backward compatibility', function () {
        $legacyData = [
            'name' => 'old-file.txt',
            'isDirectory' => true,
            'modified_at' => '2024-01-15T10:30:00Z',
            'size' => 0,
            'mode' => '755',
            'permissions' => 'rwxr-xr-x',
            'owner' => 'root',
            'group' => 'root',
            'path' => '/legacy/path',
        ];

        $fileInfo = FileInfo::fromArray($legacyData);

        expect($fileInfo->isDirectory)->toBeTrue()
            ->and($fileInfo->modifiedAt)->toBe('2024-01-15T10:30:00Z')
            ->and($fileInfo->path)->toBe('/legacy/path');
    });

    it('can be converted to array format', function () {
        $fileInfo = new FileInfo(
            name: 'test.php',
            isDirectory: false,
            size: 1536,
            modifiedAt: '2024-01-15T10:30:00Z',
            mode: '644',
            permissions: 'rw-r--r--',
            owner: 'www-data',
            group: 'www-data',
            path: '/app/test.php'
        );

        $array = $fileInfo->toArray();

        expect($array)->toBe([
            'name' => 'test.php',
            'isDir' => false,
            'size' => 1536,
            'modTime' => '2024-01-15T10:30:00Z',
            'mode' => '644',
            'permissions' => 'rw-r--r--',
            'owner' => 'www-data',
            'group' => 'www-data',
            'path' => '/app/test.php',
        ]);
    });

    it('provides backward compatibility getters', function () {
        $fileInfo = new FileInfo(
            name: 'compat.txt',
            isDirectory: true,
            size: 0,
            modifiedAt: '2024-01-15T10:30:00Z',
            mode: '755',
            permissions: 'rwxr-xr-x',
            owner: 'user',
            group: 'users'
        );

        expect($fileInfo->getIsDirectory())->toBeTrue()
            ->and($fileInfo->getModifiedAt())->toBe('2024-01-15T10:30:00Z')
            ->and($fileInfo->getPath())->toBeNull();
    });
});

describe('SearchMatch DTO', function () {
    it('can be created with search result data', function () {
        $match = new SearchMatch(
            file: '/app/src/Controller.php',
            line: 42,
            content: '// TODO: Implement better error handling'
        );

        expect($match->file)->toBe('/app/src/Controller.php')
            ->and($match->line)->toBe(42)
            ->and($match->content)->toBe('// TODO: Implement better error handling');
    });

    it('can be created from API response data', function () {
        $apiData = [
            'file' => '/app/models/User.php',
            'line' => 15,
            'content' => 'public function getName(): string // FIXME: Add validation',
        ];

        $match = SearchMatch::fromArray($apiData);

        expect($match->file)->toBe('/app/models/User.php')
            ->and($match->line)->toBe(15)
            ->and($match->content)->toBe('public function getName(): string // FIXME: Add validation');
    });

    it('can be converted to array format', function () {
        $match = new SearchMatch(
            file: '/app/config.php',
            line: 8,
            content: "define('DEBUG', true); // TODO: Set to false in production"
        );

        $array = $match->toArray();

        expect($array)->toBe([
            'file' => '/app/config.php',
            'line' => 8,
            'content' => "define('DEBUG', true); // TODO: Set to false in production",
        ]);
    });
});

describe('ReplaceRequest DTO', function () {
    it('can be created with replacement parameters', function () {
        $request = new ReplaceRequest(
            files: ['/app/config.php', '/app/settings.php'],
            pattern: 'debug = false',
            newValue: 'debug = true'
        );

        expect($request->files)->toBe(['/app/config.php', '/app/settings.php'])
            ->and($request->pattern)->toBe('debug = false')
            ->and($request->newValue)->toBe('debug = true');
    });

    it('can be created from array data', function () {
        $data = [
            'files' => ['/app/version.txt', '/app/package.json'],
            'pattern' => '"version": "1.0.0"',
            'newValue' => '"version": "1.1.0"',
        ];

        $request = ReplaceRequest::fromArray($data);

        expect($request->files)->toBe(['/app/version.txt', '/app/package.json'])
            ->and($request->pattern)->toBe('"version": "1.0.0"')
            ->and($request->newValue)->toBe('"version": "1.1.0"');
    });

    it('can be converted to array format', function () {
        $request = new ReplaceRequest(
            files: ['/app/constants.php'],
            pattern: 'const ENV = "development"',
            newValue: 'const ENV = "production"'
        );

        $array = $request->toArray();

        expect($array)->toBe([
            'files' => ['/app/constants.php'],
            'pattern' => 'const ENV = "development"',
            'newValue' => 'const ENV = "production"',
        ]);
    });
});

describe('ReplaceResult DTO', function () {
    it('can be created with successful result', function () {
        $result = new ReplaceResult(
            file: '/app/config.php',
            success: true,
            error: null
        );

        expect($result->file)->toBe('/app/config.php')
            ->and($result->success)->toBeTrue()
            ->and($result->error)->toBeNull()
            ->and($result->isSuccess())->toBeTrue()
            ->and($result->hasError())->toBeFalse();
    });

    it('can be created with error result', function () {
        $result = new ReplaceResult(
            file: '/app/readonly.txt',
            success: false,
            error: 'Permission denied'
        );

        expect($result->file)->toBe('/app/readonly.txt')
            ->and($result->success)->toBeFalse()
            ->and($result->error)->toBe('Permission denied')
            ->and($result->isSuccess())->toBeFalse()
            ->and($result->hasError())->toBeTrue();
    });

    it('can be created from API response data', function () {
        $apiData = [
            'file' => '/app/updated.php',
            'success' => true,
        ];

        $result = ReplaceResult::fromArray($apiData);

        expect($result->file)->toBe('/app/updated.php')
            ->and($result->success)->toBeTrue()
            ->and($result->error)->toBeNull();
    });

    it('filters null values when converting to array', function () {
        $result = new ReplaceResult(
            file: '/app/test.txt',
            success: true,
            error: null
        );

        $array = $result->toArray();

        expect($array)->toBe([
            'file' => '/app/test.txt',
            'success' => true,
        ]);
    });
});

describe('SearchFilesResponse DTO', function () {
    it('can be created with search results', function () {
        $files = ['/app/config.json', '/app/settings.json', '/app/package.json'];
        $response = new SearchFilesResponse($files);

        expect($response->files)->toBe($files)
            ->and($response->getCount())->toBe(3)
            ->and($response->isEmpty())->toBeFalse();
    });

    it('can be created from API response data', function () {
        $apiData = [
            'files' => ['/app/src/file1.php', '/app/src/file2.php'],
        ];

        $response = SearchFilesResponse::fromArray($apiData);

        expect($response->files)->toBe(['/app/src/file1.php', '/app/src/file2.php'])
            ->and($response->getCount())->toBe(2);
    });

    it('handles empty search results', function () {
        $response = new SearchFilesResponse([]);

        expect($response->files)->toBe([])
            ->and($response->getCount())->toBe(0)
            ->and($response->isEmpty())->toBeTrue();
    });

    it('handles missing files key in API response', function () {
        $apiData = [];
        $response = SearchFilesResponse::fromArray($apiData);

        expect($response->files)->toBe([])
            ->and($response->isEmpty())->toBeTrue();
    });

    it('can be converted to array format', function () {
        $files = ['/app/test1.txt', '/app/test2.txt'];
        $response = new SearchFilesResponse($files);

        $array = $response->toArray();

        expect($array)->toBe([
            'files' => ['/app/test1.txt', '/app/test2.txt'],
        ]);
    });
});

describe('FilePermissionsParams DTO', function () {
    it('can be created with all permission parameters', function () {
        $params = new FilePermissionsParams(
            mode: '644',
            owner: 'www-data',
            group: 'www-data'
        );

        expect($params->mode)->toBe('644')
            ->and($params->owner)->toBe('www-data')
            ->and($params->group)->toBe('www-data')
            ->and($params->hasMode())->toBeTrue()
            ->and($params->hasOwner())->toBeTrue()
            ->and($params->hasGroup())->toBeTrue()
            ->and($params->isEmpty())->toBeFalse();
    });

    it('can be created with partial parameters', function () {
        $params = new FilePermissionsParams(mode: '755');

        expect($params->mode)->toBe('755')
            ->and($params->owner)->toBeNull()
            ->and($params->group)->toBeNull()
            ->and($params->hasMode())->toBeTrue()
            ->and($params->hasOwner())->toBeFalse()
            ->and($params->hasGroup())->toBeFalse()
            ->and($params->isEmpty())->toBeFalse();
    });

    it('can be created empty', function () {
        $params = new FilePermissionsParams;

        expect($params->mode)->toBeNull()
            ->and($params->owner)->toBeNull()
            ->and($params->group)->toBeNull()
            ->and($params->isEmpty())->toBeTrue();
    });

    it('can be created from array data', function () {
        $data = [
            'mode' => '755',
            'owner' => 'user',
            'group' => 'users',
        ];

        $params = FilePermissionsParams::fromArray($data);

        expect($params->mode)->toBe('755')
            ->and($params->owner)->toBe('user')
            ->and($params->group)->toBe('users');
    });

    it('filters null values when converting to array', function () {
        $params = new FilePermissionsParams(
            mode: '644',
            owner: null,
            group: 'staff'
        );

        $array = $params->toArray();

        expect($array)->toBe([
            'mode' => '644',
            'group' => 'staff',
        ]);
    });
});
