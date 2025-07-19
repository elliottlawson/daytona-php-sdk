<?php

use ElliottLawson\Daytona\Tests\TestCase;
use ElliottLawson\Daytona\Tests\Integration\IntegrationTestCase;

// Use TestCase for unit tests
uses(TestCase::class)->in('Feature');

// Use IntegrationTestCase for integration tests
uses(IntegrationTestCase::class)->in('Integration');
