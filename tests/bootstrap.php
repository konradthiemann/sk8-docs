<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$debug = $_SERVER['APP_DEBUG'] ?? '1';
if (is_scalar($debug) && filter_var((string) $debug, \FILTER_VALIDATE_BOOL)) {
    umask(0000);
}
