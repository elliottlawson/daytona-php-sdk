<?php

use ElliottLawson\Daytona\Tests\Integration\IntegrationTestCase;
use ElliottLawson\Daytona\Tests\TestCase;

uses(TestCase::class)->in('Feature');

uses(IntegrationTestCase::class)
    ->group('integration')
    ->in('Integration');
