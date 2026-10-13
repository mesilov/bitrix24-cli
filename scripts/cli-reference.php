<?php

declare(strict_types=1);

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Console\TaskUsage;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$application = (new ApplicationFactory())->create($root);
$commands = $application->all();
ksort($commands);
$rows = [];
$metadata = [];
foreach ($commands as $name => $command) {
    if ($command->isHidden()) {
        continue;
    }

    $entry = CommandCatalog::all()[$name] ?? null;
    $examples = match ($name) {
        'list' => ['b24cli list task'],
        'help' => ['b24cli help task:add'],
        'completion' => ['b24cli completion bash'],
        default => TaskUsage::examples($command),
    };
    $test = null;
    $source = null;
    if ($entry !== null) {
        $source = 'src/' . str_replace('\\', '/', substr($entry['class'], strlen('Bitrix24\\CLI\\'))) . '.php';
        $parts = explode('\\', $entry['class']);
        $candidate = 'tests/Integration/' . $parts[count($parts) - 2] . '/' . end($parts) . 'Test.php';
        if (is_file($root . '/' . $candidate)) {
            $test = $candidate;
        }
    }

    $label = $source === null ? '`' . $name . '`' : '[`' . $name . '`](../' . $source . ')';
    $coverage = $test === null ? 'Нет отдельного portal-теста; [offline help](../tests/Unit/HelpAndAboutTest.php)' : '[`' . basename($test) . '`](../' . $test . ')';
    $effect = $entry['effect'] ?? 'offline';
    $rows[] = '| ' . $label . ' | ' . $command->getDescription() . ' | ' . $effect . ' | `' . str_replace('|', '\\|', $examples[0]) . '` | ' . $coverage . ' |';
    $metadata[] = ['name' => $name, 'description' => $command->getDescription(), 'effect' => $effect, 'examples' => $examples, 'source' => $source, 'integrationTest' => $test];
}

$document = "# Карта CLI-вызовов\n\n"
    . 'Каталог содержит ' . count($rows) . " публичных команд. Проверяйте установленную сборку через `b24cli --version` и `b24cli list`. Полная справка: `b24cli COMMAND --help` или `b24cli help COMMAND`.\n\n"
    . "`b24cli --help`/`-h` показывают назначение, список команд, глобальные опции и примеры задач. `b24cli --about` показывает назначение, версию, автора, лицензию и ссылки на [приложение](https://github.com/mesilov/bitrix24-cli) и [скилл](https://github.com/mesilov/bitrix24-cli-skill). Эти вызовы не требуют подключения и не обращаются к порталу. `list --help` описывает именно list; вызов без аргументов выводит список команд.\n\n"
    . "## Команды\n\n| Команда | Назначение | Эффект | Пример | Интеграционное покрытие |\n| --- | --- | --- | --- | --- |\n"
    . implode("\n", $rows) . "\n\n"
    . "## Ограничения и справка\n\n"
    . "ID в примерах — образцы, замените их доступными вам сущностями. `write`/`delete` примеры используют `--dry-run`: записи не выполняются, но проверочные чтения могут требовать подключения и прав. Dry-run не доказывает приёмку сервером. `local` читает или изменяет локальную конфигурацию; `connection:add` требует скрытого ввода webhook в терминале либо `--webhook-stdin` с redirected stdin, `connection:remove` удаляет локальный профиль. `read` зависит от прав, бюджета обхода и полноты данных.\n\n"
    . "Аргументы, опции, defaults, формат вывода и ограничения смотрите в help соответствующей команды. Selectors — в [конфигурации](configuration.md); запуск и тесты — в [разработке](development.md). Файл интеграционного теста показывает наличие проверок, а не успешную live-приёмку или полное покрытие бизнес-сценария.\n\n"
    . "## JTBD и актуализация\n\n"
    . "Связь рабочих сценариев с командами и тестами — в [JTBD задач по ролям](task-jtbd.md). Планируемые команды и границы покрытия отмечены там отдельно; в этот каталог входят только зарегистрированные публичные команды.\n\n"
    . "Карта формируется из текущего каталога, примеров help и существующих файлов тестов. Обновление: `make cli-reference`; проверка: `make cli-reference-check`. JSON для сверки скилла: `sh scripts/compose.sh run --rm -T php-cli php scripts/cli-reference.php --json`. Повторная проверка финальной 0.2.0 — [issue #109](https://github.com/mesilov/bitrix24-cli/issues/109).\n";

$mode = $argv[1] ?? '--check';
if ($mode === '--json') {
    echo json_encode(['version' => $application->getVersion(), 'commands' => $metadata], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    exit(0);
}

$path = $root . '/docs/cli-command-map.md';
if ($mode === '--write') {
    file_put_contents($path, $document);
    echo "Updated docs/cli-command-map.md\n";
    exit(0);
}

if ($mode !== '--check') {
    fwrite(STDERR, "Use --write, --check or --json.\n");
    exit(2);
}

if (!is_file($path) || file_get_contents($path) !== $document) {
    fwrite(STDERR, "CLI map is stale; run make cli-reference.\n");
    exit(1);
}

echo 'CLI map matches ' . count($rows) . " public commands.\n";
