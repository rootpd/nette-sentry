<?php declare(strict_types = 1);

use Ninjify\Nunjuck\Environment;

require __DIR__ . '/../vendor/autoload.php';

// Configure environment
Environment::setupTester();
Environment::setupTimezone();
// Suppress deprecation warning from ninjify/nunjuck (uses deprecated lcg_value() in PHP 8.4+)
@Environment::setupVariables(__DIR__);
