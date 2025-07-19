<?php

use ElliottLawson\Daytona\Tests\Integration\IntegrationTestCase;
use ElliottLawson\Daytona\Tests\TestCase;

// Use TestCase for unit tests
uses(TestCase::class)->in('Feature');

// Use IntegrationTestCase for integration tests
uses(IntegrationTestCase::class)->in('Integration');
