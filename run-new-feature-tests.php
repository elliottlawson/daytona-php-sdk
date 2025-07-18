#!/usr/bin/env php
<?php

/**
 * Test Runner for New Daytona PHP SDK Features
 *
 * This script runs only the tests for the newly implemented functionality:
 * - Sandbox waiting mechanisms
 * - Sandbox discovery & filtering
 * - Centralized error handling
 */
echo "🚀 Running Daytona PHP SDK New Feature Tests\n";
echo '='.str_repeat('=', 50)."\n\n";

$testFiles = [
    'tests/Feature/SandboxWaitingTest.php' => 'Sandbox Waiting Mechanisms',
    'tests/Feature/SandboxDiscoveryTest.php' => 'Sandbox Discovery & Filtering',
    'tests/Feature/CentralizedErrorHandlingTest.php' => 'Centralized Error Handling',
];

$allPassed = true;
$totalTests = 0;
$passedTests = 0;

foreach ($testFiles as $testFile => $description) {
    echo "📋 Testing: $description\n";
    echo "   File: $testFile\n";

    if (! file_exists($testFile)) {
        echo "   ❌ Test file not found!\n\n";
        $allPassed = false;

        continue;
    }

    // Run the specific test file
    $command = "vendor/bin/pest $testFile --colors=always";

    echo "   🧪 Running tests...\n";

    $output = [];
    $returnCode = 0;
    exec($command.' 2>&1', $output, $returnCode);

    $outputStr = implode("\n", $output);

    // Parse test results
    if (preg_match('/(\d+) passed/', $outputStr, $matches)) {
        $passed = (int) $matches[1];
        $totalTests += $passed;
        $passedTests += $passed;
        echo "   ✅ $passed tests passed\n";
    } else {
        echo "   ❌ Tests failed or no results found\n";
        $allPassed = false;
    }

    if ($returnCode !== 0) {
        echo "   ⚠️  Some tests may have failed. Full output:\n";
        echo '   '.str_replace("\n", "\n   ", $outputStr)."\n";
        $allPassed = false;
    }

    echo "\n";
}

echo '='.str_repeat('=', 50)."\n";

if ($allPassed && $totalTests > 0) {
    echo "🎉 ALL NEW FEATURE TESTS PASSED! ($passedTests/$totalTests)\n\n";
    echo "✅ Your new functionality is working correctly:\n";
    echo "   • Sandbox waiting mechanisms - eliminating race conditions\n";
    echo "   • Sandbox discovery & filtering - finding existing sandboxes\n";
    echo "   • Centralized error handling - consistent error management\n\n";
    echo "🚀 Your SDK is ready for production use!\n";
    exit(0);
} else {
    echo "❌ Some tests failed or couldn't run.\n\n";
    echo "💡 Troubleshooting tips:\n";
    echo "   • Make sure you've run: composer install\n";
    echo "   • Ensure Pest is installed: composer require pestphp/pest --dev\n";
    echo "   • Check that all new files are in the correct locations\n";
    echo "   • Review any error messages above\n\n";
    exit(1);
}
