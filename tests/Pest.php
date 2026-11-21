<?php

use ElliottLawson\Daytona\Tests\Integration\IntegrationTestCase;
use ElliottLawson\Daytona\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(TestCase::class)->in('Feature');

uses(IntegrationTestCase::class)
    ->group('integration')
    ->in('Integration');

/**
 * Fake the sandbox lookup the client makes to discover a sandbox's toolbox proxy URL.
 * Only answers GET {api}/sandbox/{id}, so other sandbox endpoints fall through to
 * the test's own fakes.
 */
function fakeToolboxProxy(string $proxyUrl = 'https://proxy.test/toolbox'): void
{
    Http::fake(function (Request $request) use ($proxyUrl) {
        if ($request->method() !== 'GET' || ! preg_match('#/sandbox/([^/?]+)$#', $request->url(), $matches)) {
            return null;
        }

        return Http::response([
            'id' => $matches[1],
            'state' => 'started',
            'toolboxProxyUrl' => $proxyUrl,
        ]);
    });
}
