<?php

use ElliottLawson\Daytona\CommandResponseParser;

it('parses standard response format', function () {
    $response = [
        'exitCode' => 0,
        'stdout' => 'Hello World',
        'stderr' => '',
    ];

    $parsed = CommandResponseParser::parse($response);

    expect($parsed->exitCode)->toBe(0);
    expect($parsed->output)->toBe('Hello World');
    expect($parsed->errorOutput)->toBe('');
});

it('parses alternative field names', function () {
    $response = [
        'exit_code' => 1,
        'output' => 'Command output',
        'error' => 'Error message',
    ];

    $parsed = CommandResponseParser::parse($response);

    expect($parsed->exitCode)->toBe(1);
    expect($parsed->output)->toBe('Command output');
    expect($parsed->errorOutput)->toBe('Error message');
});

it('parses nested result format', function () {
    $response = [
        'exitCode' => -1,
        'result' => [
            'exitCode' => 0,
            'stdout' => 'Nested output',
            'stderr' => 'Nested error',
        ],
    ];

    $parsed = CommandResponseParser::parse($response);

    expect($parsed->exitCode)->toBe(0);
    expect($parsed->output)->toBe('Nested output');
    expect($parsed->errorOutput)->toBe('Nested error');
});

it('parses string result with -1 exit code', function () {
    $response = [
        'exitCode' => -1,
        'result' => 'Simple string output',
    ];

    $parsed = CommandResponseParser::parse($response);

    expect($parsed->exitCode)->toBe(-1);
    expect($parsed->output)->toBe('Simple string output');
    expect($parsed->errorOutput)->toBe('');
});

it('handles result field as primary output', function () {
    $response = [
        'exitCode' => 0,
        'result' => 'Result field output',
    ];

    $parsed = CommandResponseParser::parse($response);

    expect($parsed->exitCode)->toBe(0);
    expect($parsed->output)->toBe('Result field output');
    expect($parsed->errorOutput)->toBe('');
});

it('provides defaults for missing fields', function () {
    $response = [];

    $parsed = CommandResponseParser::parse($response);

    expect($parsed->exitCode)->toBe(0);
    expect($parsed->output)->toBe('');
    expect($parsed->errorOutput)->toBe('');
});

it('provides helpful methods on CommandResponse', function () {
    $successResponse = CommandResponseParser::parse([
        'exitCode' => 0,
        'stdout' => 'Success',
        'stderr' => '',
    ]);

    expect($successResponse->isSuccessful())->toBeTrue();
    expect($successResponse->failed())->toBeFalse();
    expect($successResponse->hasOutput())->toBeTrue();
    expect($successResponse->hasErrorOutput())->toBeFalse();
    expect($successResponse->hasKnownExitCode())->toBeTrue();

    $failureResponse = CommandResponseParser::parse([
        'exitCode' => 1,
        'stdout' => '',
        'stderr' => 'Error occurred',
    ]);

    expect($failureResponse->isSuccessful())->toBeFalse();
    expect($failureResponse->failed())->toBeTrue();
    expect($failureResponse->hasOutput())->toBeFalse();
    expect($failureResponse->hasErrorOutput())->toBeTrue();
    expect($failureResponse->hasKnownExitCode())->toBeTrue();
});

it('handles unknown exit code (-1) correctly', function () {
    $unknownResponse = CommandResponseParser::parse([
        'exitCode' => -1,
        'result' => '',
    ]);

    expect($unknownResponse->exitCode)->toBe(-1);
    expect($unknownResponse->isSuccessful())->toBeFalse();
    expect($unknownResponse->failed())->toBeTrue();
    expect($unknownResponse->hasKnownExitCode())->toBeFalse();
    expect($unknownResponse->output)->toBe('');
    expect($unknownResponse->errorOutput)->toBe('');
});

it('handles unknown exit code with output', function () {
    $unknownResponse = CommandResponseParser::parse([
        'exitCode' => -1,
        'result' => 'Command output when exit code is unknown',
    ]);

    expect($unknownResponse->exitCode)->toBe(-1);
    expect($unknownResponse->hasKnownExitCode())->toBeFalse();
    expect($unknownResponse->hasOutput())->toBeTrue();
    expect($unknownResponse->output)->toBe('Command output when exit code is unknown');
});
