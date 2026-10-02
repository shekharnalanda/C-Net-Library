<?php

// Catch helper exceptions before Laravel's global handler can hide CLI output.
array_shift($argv);
$argc = count($argv);
$_SERVER['argv'] = $argv;
$_SERVER['argc'] = $argc;
try {
    if (! isset($argv[0]) || ! is_file($argv[0])) {
        throw new RuntimeException('Deployment helper is missing.');
    }
    require $argv[0];
} catch (Throwable $e) {
    fwrite(STDERR, 'HELPER_FAILED | '.get_class($e).' | '.$e->getMessage()."\n");
    exit(1);
}
