<?php declare(strict_types = 1);

use Ninjify\Nunjuck\Environment;

require __DIR__ . '/../vendor/autoload.php';

// Configure environment
Environment::setupTester();
Environment::setupTimezone();
Environment::setupVariables(__DIR__);
