<?php

declare(strict_types=1);

use Bitrix24\CLI\Application\Connection\ScopeMatrix;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Symfony\Component\Console\Command\Command;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$path = $root . '/docs/auth-scopes.md';
$public = array_keys(array_filter((new ApplicationFactory())->create($root)->all(), static fn (Command $command): bool => !$command->isHidden()));
$block = (new ScopeMatrix())->render($public);

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--write', '--check'], true)) {
    fwrite(STDERR, "Используйте --write или --check.\n");
    exit(2);
}

$document = is_file($path) ? (string) file_get_contents($path) : '';
$start = strpos($document, ScopeMatrix::START);
$end = strpos($document, ScopeMatrix::END);
if ($start === false || $end === false || $end < $start) {
    fwrite(STDERR, 'В docs/auth-scopes.md нет маркеров ' . ScopeMatrix::START . ' и ' . ScopeMatrix::END . ".\n");
    exit(1);
}

$updated = substr($document, 0, $start) . $block . substr($document, $end + strlen(ScopeMatrix::END));
if ($mode === '--write') {
    file_put_contents($path, $updated);
    echo "Обновлена матрица разрешений в docs/auth-scopes.md\n";
    exit(0);
}

if ($updated !== $document) {
    fwrite(STDERR, "Матрица разрешений устарела; выполните make scope-matrix.\n");
    exit(1);
}
