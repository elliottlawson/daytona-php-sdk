<?php

use ElliottLawson\Daytona\DaytonaClient;
use ElliottLawson\Daytona\DTOs\Config;
use ElliottLawson\Daytona\Exceptions\ApiException;
use ElliottLawson\Daytona\Exceptions\DaytonaException;
use ElliottLawson\Daytona\Exceptions\SandboxException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $config = new Config(
        apiKey: 'test-api-key',
        apiUrl: 'https://api.example.com',
        organizationId: 'test-org'
    );
    $this->client = new DaytonaClient($config);
});

describe('Centralized Error Handling', function () {

    describe('Base DaytonaException', function () {
        it('allows catching all SDK exceptions with base class', function () {
            Http::fake([
                '*/sandbox/test-sandbox' => Http::response(['error' => 'Not found'], 404),
            ]);

            try {
                $this->client->getSandbox('test-sandbox');
                expect(false)->toBeTrue(); // Should not reach this line
            } catch (DaytonaException $e) {
                expect($e)->toBeInstanceOf(DaytonaException::class);
            }
        });

        it('specific exceptions extend DaytonaException', function () {
            expect(new ApiException('test'))->toBeInstanceOf(DaytonaException::class)
                ->and(new SandboxException('test'))->toBeInstanceOf(DaytonaException::class);
        });

        it('provides factory methods for common scenarios', function () {
            $notFoundException = DaytonaException::notFound('sandbox-123');
            $timeoutException = DaytonaException::timeout('start', 60);
            $invalidArgException = DaytonaException::invalidArgument('timeout', 'must be positive');

            expect($notFoundException->getMessage())->toContain('Resource not found: sandbox-123')
                ->and($timeoutException->getMessage())->toContain('Operation \'start\' timed out after 60 seconds')
                ->and($invalidArgException->getMessage())->toContain('Invalid argument \'timeout\': must be positive');
        });
    });

    describe('Centralized HTTP Error Handling', function () {
        it('handles 401 unauthorized errors', function () {
            Http::fake([
                '*/sandbox' => Http::response(['error' => 'Unauthorized'], 401),
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'Authentication failed. Please check your API key.');

        it('handles 403 forbidden errors', function () {
            Http::fake([
                '*/sandbox' => Http::response(['error' => 'Forbidden'], 403),
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'Access denied. Please check your permissions.');

        it('handles 404 not found errors', function () {
            Http::fake([
                '*/sandbox/nonexistent' => Http::response(['error' => 'Not found'], 404),
            ]);

            $this->client->getSandbox('nonexistent');
        })->throws(ApiException::class, 'Resource not found.');

        it('handles 409 conflict errors', function () {
            Http::fake([
                '*/sandbox' => Http::response(['error' => 'Conflict'], 409),
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'Conflict - resource already exists or is in use.');

        it('handles 422 validation errors', function () {
            Http::fake([
                '*/sandbox' => Http::response(['error' => 'Invalid data'], 422),
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'Invalid request data. Please check your parameters.');

        it('handles 429 rate limit errors', function () {
            Http::fake([
                '*/sandbox' => Http::response(['error' => 'Rate limited'], 429),
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'Rate limit exceeded. Please try again later.');

        it('handles 500 server errors', function () {
            Http::fake([
                '*/sandbox' => Http::response(['error' => 'Internal server error'], 500),
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'Server error. Please try again later.');

        it('handles 502 bad gateway errors', function () {
            Http::fake([
                '*/sandbox' => Http::response(['error' => 'Bad gateway'], 502),
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'Server error. Please try again later.');

        it('handles 503 service unavailable errors', function () {
            Http::fake([
                '*/sandbox' => Http::response(['error' => 'Service unavailable'], 503),
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'Server error. Please try again later.');

        it('handles 504 gateway timeout errors', function () {
            Http::fake([
                '*/sandbox' => Http::response(['error' => 'Gateway timeout'], 504),
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'Server error. Please try again later.');

        it('handles unknown status codes with default message', function () {
            Http::fake([
                '*/sandbox' => Http::response(['error' => 'Unknown error'], 418), // I'm a teapot
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'API request failed');
    });

    describe('Enhanced Error Context', function () {
        it('preserves response data in exception', function () {
            $errorBody = json_encode(['error' => 'Custom error message', 'code' => 'ERR_001']);

            Http::fake([
                '*/sandbox/test' => Http::response($errorBody, 400),
            ]);

            try {
                $this->client->getSandbox('test');
            } catch (ApiException $e) {
                expect($e->getResponseBody())->toBe($errorBody)
                    ->and($e->getStatusCode())->toBe(400);
            }
        });

        it('includes response object for debugging', function () {
            Http::fake([
                '*/sandbox/test' => Http::response(['error' => 'Test error'], 400),
            ]);

            try {
                $this->client->getSandbox('test');
            } catch (ApiException $e) {
                $response = $e->getResponse();
                expect($response)->not()->toBeNull()
                    ->and($response->status())->toBe(400);
            }
        });
    });

    describe('Timeout Error Handling', function () {
        it('handles connection timeout errors', function () {
            Http::fake(function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            });

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'Network error during list sandboxes. Please check your connection.');

        it('handles request timeout in error handler', function () {
            // Mock a scenario where timeout is detected in the error handler
            Http::fake([
                '*/sandbox' => function () {
                    throw new \Exception('timeout of 30000ms exceeded');
                },
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class);
    });

    describe('Error Handling Integration', function () {
        it('centralized error handling applies to all client methods', function () {
            Http::fake([
                '*/sandbox/test/start' => Http::response(['error' => 'Unauthorized'], 401),
                '*/sandbox/test/stop' => Http::response(['error' => 'Forbidden'], 403),
                '*/sandbox/test' => Http::response(['error' => 'Not found'], 404),
                '*/toolbox/test/toolbox/process/execute' => Http::response(['error' => 'Server error'], 500),
            ]);

            // Test multiple methods use centralized error handling
            expect(fn () => $this->client->startSandbox('test'))->toThrow(ApiException::class)
                ->and(fn () => $this->client->stopSandbox('test'))->toThrow(ApiException::class)
                ->and(fn () => $this->client->getSandbox('test'))->toThrow(ApiException::class)
                ->and(fn () => $this->client->executeCommand('test', 'echo hello'))->toThrow(ApiException::class);
        });

        it('sandbox waiting methods get centralized error handling', function () {
            Http::fake([
                '*/sandbox/test/start' => Http::response(['id' => 'test', 'state' => 'starting'], 200),
                '*/sandbox/test' => Http::response(['error' => 'Forbidden'], 403),
            ]);

            // Start succeeds but status check fails with centralized error handling
            $this->client->startSandbox('test');
        })->throws(ApiException::class, 'Access denied');

        it('sandbox discovery methods get centralized error handling', function () {
            Http::fake([
                '*/sandbox' => Http::response(['error' => 'Rate limited'], 429),
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'Rate limit exceeded');
    });

    describe('Error Message Consistency', function () {
        it('provides consistent error messages across different operations', function () {
            Http::fake([
                '*/sandbox/test' => Http::response(['error' => 'Not found'], 404),
                '*/toolbox/test/toolbox/files/download*' => Http::response(['error' => 'Not found'], 404),
            ]);

            $getSandboxException = null;
            $readFileException = null;

            try {
                $this->client->getSandbox('test');
            } catch (ApiException $e) {
                $getSandboxException = $e;
            }

            try {
                $this->client->readFile('test', '/nonexistent');
            } catch (ApiException $e) {
                $readFileException = $e;
            }

            expect($getSandboxException->getMessage())->toBe($readFileException->getMessage());
        });

        it('preserves original error details in default case', function () {
            $customError = 'Custom API error with specific details';
            Http::fake([
                '*/sandbox' => Http::response($customError, 418),
            ]);

            $this->client->listSandboxes();
        })->throws(ApiException::class, 'Custom API error with specific details');
    });

    describe('Backward Compatibility', function () {
        it('still throws specific exception types for specific operations', function () {
            Http::fake([
                '*/sandbox/test/start' => Http::response(['id' => 'test', 'state' => 'starting'], 200),
                '*/sandbox/test' => Http::response([
                    'id' => 'test',
                    'state' => 'error',
                    'errorReason' => 'Failed to start',
                ], 200),
            ]);

            // Should throw SandboxException for state errors, not just ApiException
            $this->client->startSandbox('test');
        })->throws(SandboxException::class, 'entered error state');

        it('maintains exception hierarchy', function () {
            Http::fake([
                '*/sandbox' => Http::response(['error' => 'Server error'], 500),
            ]);

            try {
                $this->client->listSandboxes();
            } catch (ApiException $e) {
                expect($e)->toBeInstanceOf(ApiException::class)
                    ->and($e)->toBeInstanceOf(DaytonaException::class);
            }
        });
    });
});
