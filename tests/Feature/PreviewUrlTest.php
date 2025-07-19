<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\DTOs\PortPreviewUrl;
use ElliottLawson\Daytona\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->sandboxId = 'test-sandbox-123';
    $this->config = new Config(
        apiKey: 'test-key',
        apiUrl: 'https://api.daytona.io',
        organizationId: 'test-org'
    );
    $this->client = new DaytonaClient($this->config);
});

it('can get preview url for sandbox port', function () {
    // Arrange
    $port = 3000;
    $expectedUrl = 'https://3000-test-sandbox-123.h7890.daytona.work';
    $expectedToken = 'vg5c0ylmcimr8b_v1ne0u6mdnvit6gc0';

    Http::fake([
        "*/sandbox/{$this->sandboxId}" => Http::response([
            'id' => $this->sandboxId,
            'state' => 'started',
        ], 200),
        "*/sandbox/{$this->sandboxId}/ports/{$port}/preview-url" => Http::response([
            'url' => $expectedUrl,
            'token' => $expectedToken,
            'legacyProxyUrl' => 'https://3000-test-sandbox-123.runner.daytona.work',
        ], 200),
    ]);

    // Act
    $sandbox = $this->client->getSandboxById($this->sandboxId);
    $previewInfo = $sandbox->getPreviewLink($port);

    // Assert
    expect($previewInfo)->toBeInstanceOf(PortPreviewUrl::class);
    expect($previewInfo->url)->toBe($expectedUrl);
    expect($previewInfo->token)->toBe($expectedToken);
    expect($previewInfo->legacyProxyUrl)->toBe('https://3000-test-sandbox-123.runner.daytona.work');
});

it('can get preview url without legacy proxy url', function () {
    // Arrange
    $port = 8080;
    $expectedUrl = 'https://8080-test-sandbox-123.h7890.daytona.work';
    $expectedToken = 'another-token-123';

    Http::fake([
        "*/sandbox/{$this->sandboxId}" => Http::response([
            'id' => $this->sandboxId,
            'state' => 'started',
        ], 200),
        "*/sandbox/{$this->sandboxId}/ports/{$port}/preview-url" => Http::response([
            'url' => $expectedUrl,
            'token' => $expectedToken,
        ], 200),
    ]);

    // Act
    $sandbox = $this->client->getSandboxById($this->sandboxId);
    $previewInfo = $sandbox->getPreviewLink($port);

    // Assert
    expect($previewInfo->url)->toBe($expectedUrl);
    expect($previewInfo->token)->toBe($expectedToken);
    expect($previewInfo->legacyProxyUrl)->toBeNull();
});

it('throws exception when preview url request fails', function () {
    // Arrange
    $port = 3000;

    Http::fake([
        "*/sandbox/{$this->sandboxId}" => Http::response([
            'id' => $this->sandboxId,
            'state' => 'started',
        ], 200),
        "*/sandbox/{$this->sandboxId}/ports/{$port}/preview-url" => Http::response([
            'error' => 'Port not exposed',
        ], 404),
    ]);

    // Act & Assert
    $sandbox = $this->client->getSandboxById($this->sandboxId);

    expect(fn () => $sandbox->getPreviewLink($port))
        ->toThrow(ApiException::class, 'Resource not found');
});

it('handles server errors when getting preview url', function () {
    // Arrange
    $port = 3000;

    Http::fake([
        "*/sandbox/{$this->sandboxId}" => Http::response([
            'id' => $this->sandboxId,
            'state' => 'started',
        ], 200),
        "*/sandbox/{$this->sandboxId}/ports/{$port}/preview-url" => Http::response([
            'error' => 'Internal server error',
        ], 500),
    ]);

    // Act & Assert
    $sandbox = $this->client->getSandboxById($this->sandboxId);

    expect(fn () => $sandbox->getPreviewLink($port))
        ->toThrow(ApiException::class, 'Server error');
});

it('port preview url dto can convert to and from array', function () {
    // Arrange
    $data = [
        'url' => 'https://3000-sandbox-123.daytona.work',
        'token' => 'test-token-123',
        'legacyProxyUrl' => 'https://3000-sandbox-123.runner.daytona.work',
    ];

    // Act
    $dto = PortPreviewUrl::fromArray($data);
    $array = $dto->toArray();

    // Assert
    expect($dto->url)->toBe($data['url']);
    expect($dto->token)->toBe($data['token']);
    expect($dto->legacyProxyUrl)->toBe($data['legacyProxyUrl']);
    expect($array)->toBe($data);
});

it('port preview url dto filters null values in to array', function () {
    // Arrange
    $data = [
        'url' => 'https://3000-sandbox-123.daytona.work',
        'token' => 'test-token-123',
    ];

    // Act
    $dto = PortPreviewUrl::fromArray($data);
    $array = $dto->toArray();

    // Assert
    expect($array)->not->toHaveKey('legacyProxyUrl');
    expect($array)->toHaveCount(2);
});
