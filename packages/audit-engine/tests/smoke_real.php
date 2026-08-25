<?php

/** Temporary smoke: run the deep engine against a real site. */

require __DIR__.'/../src/autoload.php';

use GeoPro\AuditEngine\AuditEngine;
use GeoPro\AuditEngine\CurlWebPageFetcher;

$url = $argv[1] ?? 'https://docuconsist.com/';
$engine = new AuditEngine(new CurlWebPageFetcher());
$result = $engine->run($url);

if ($result->error !== null) {
    echo 'ERROR: ', $result->error, PHP_EOL;

    exit(1);
}

echo 'url          = ', $result->url, PHP_EOL;
echo 'readiness    = ', $result->score, '%', PHP_EOL;
echo 'passed       = ', $result->passedChecks, '/', $result->totalChecks, PHP_EOL;
echo str_repeat('-', 60), PHP_EOL;

foreach ($result->categories as $cat) {
    echo sprintf("%-12s %d/%d\n", $cat['id'], $cat['passed'], $cat['total']);
    foreach ($cat['issues'] as $issue) {
        echo sprintf("   [%-8s] %s\n", $issue['severity'], $issue['code']);
    }
}
