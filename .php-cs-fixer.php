<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->name('*.php')
    ->append([__DIR__ . '/bin/console'])
    ->ignoreVCS(true);

return (new Config())
    ->setCacheFile(__DIR__ . '/var/cache/php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules(['@PSR12' => true]);
