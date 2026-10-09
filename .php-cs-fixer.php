<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/config'])
    ->name('*.php')
    ->append([__DIR__ . '/bin/console', __DIR__ . '/bin/b24cli'])
    ->ignoreVCS(true);

return (new Config())
    ->setCacheFile(__DIR__ . '/var/cache/php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules(['@PSR12' => true]);
