<?php

require __DIR__.'/../vendor/autoload.php';

// Load .env.testing file
if (file_exists(__DIR__.'/../.env.testing')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..', '.env.testing');
    $dotenv->load();
}

// Then load Orchestra's bootstrap
require __DIR__.'/../vendor/orchestra/testbench-core/laravel/bootstrap/app.php';
