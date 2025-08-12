<?php

declare(strict_types=1);

use Bakame\Stackwatch\Console\Logger;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Stackwatch;
use Symfony\Component\Console\Output\StreamOutput;

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} elseif (is_file(__DIR__ . '/../../autoload.php')) {
    require __DIR__ . '/../../autoload.php';
} else {
    fwrite(STDERR, 'Cannot find the vendor directory, have you executed composer install?' . PHP_EOL);
    fwrite(STDERR,'See https://getcomposer.org to get Composer.' . PHP_EOL);

    exit(1);
}

if (!class_exists(StreamOutput::class)) {
    fwrite(STDERR, 'The symfony/console package is required to use the command line.'.PHP_EOL);

    exit(1);
}

if ('cli' !== PHP_SAPI) {
    fwrite(STDERR, 'This script must be run from the command line.'.PHP_EOL);

    exit(1);
}

function updateIniSettings(array $argv): void
{
    foreach ($argv as $arg) {
        if (1 === preg_match('/^--memory-limit=(?<memory>.+)$/', $arg, $m)) {
            ini_set('memory_limit', $m['memory']);
            break;
        }
    }
}

updateIniSettings($argv);

$stderr = new StreamOutput(STDERR);

return new Stackwatch(
    stdout: new StreamOutput(STDOUT),
    stderr: $stderr,
    logger: new Logger($stderr),
    environment: Environment::current()
);
