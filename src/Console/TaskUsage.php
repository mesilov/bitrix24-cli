<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Symfony\Component\Console\Command\Command;

final class TaskUsage
{
    public static function examples(Command $command): array
    {
        $examples = match ($command->getName()) {
            'doctor:problem-trajectory' => ['b24cli doctor:problem-trajectory --action status --json', 'b24cli doctor:problem-trajectory --report-file report.json --json'],
            'auth:login' => ['b24cli auth:login work', 'b24cli auth:login work --replace', 'b24cli auth:login ci --webhook-stdin --skip-check'],
            'auth:status' => ['b24cli auth:status work --json', 'b24cli auth:status --env-file /absolute/path/portal.env'],
            'auth:list' => ['b24cli auth:list', 'b24cli auth:list work --json'],
            'auth:switch' => ['b24cli auth:switch work'],
            'auth:logout' => ['b24cli auth:logout work'],
            'config:list' => ['b24cli config:list', 'b24cli config:list --profile work --json'],
            'config:get' => ['b24cli config:get default.task.groupId', 'b24cli config:get logging.level --plain'],
            'config:set' => ['b24cli config:set default.task.groupId 187', 'b24cli config:set default.users.observerId 5 --profile work', 'b24cli config:set logging.enabled true'],
            'config:unset' => ['b24cli config:unset default.task.groupId', 'b24cli config:unset logging.level'],
            'users:current' => ['b24cli users:current --json'],
            'users:find' => ['b24cli users:find --name "Иванов Иван" --json'],
            'users:colleagues' => ['b24cli users:colleagues --department-id 7 --json'],
            'users:manager' => ['b24cli users:manager --department-id 7 --json'],
            'task:add' => ['b24cli task:add --title "Подготовить договор" --creator 1 --responsible 42'],
            'task:show' => ['b24cli task:show 123'],
            'task:my' => ['b24cli task:my --user 42 --open --json'],
            'task:list' => ['b24cli task:list --group-id 7 --responsible 42'],
            'task:find' => ['b24cli task:find --title "договор" --group-id 7 --responsible 42', 'b24cli task:find --title "договор" --role responsible --all --json'],
            'task:update' => ['b24cli task:update 123 --title "Договор для согласования"', 'b24cli task:update 123 --responsible 42', 'b24cli task:update 123 --description "Проверить реквизиты"'],
            'task:delete' => ['b24cli task:delete 123 --dry-run'],
            'task:time:add' => ['b24cli task:time:add 123 --seconds 3600 --text "Подготовка договора"', 'b24cli task:time:add 123 --seconds 3600 --created-date 2026-10-22T10:00:00+03:00'],
            'task:time:update' => ['b24cli task:time:update 123 --entry 30 --seconds 1800', 'b24cli task:time:update 123 --entry 30 --seconds 1800 --created-date 2026-10-22T10:00:00+03:00'],
            default => preg_match('/Пример(?:ы)?:\s*(b24cli[^;\n]*)/u', $command->getHelp(), $match) ? [trim(preg_split('/\.(?:\s|$)/u', $match[1])[0])] : ['b24cli ' . $command->getName() . ' --help'],
        };
        if ($command->getDefinition()->hasOption('dry-run') && $command->getName() !== 'auth:status') {
            return array_map(static fn (string $example): string => str_contains($example, '--dry-run') ? $example : $example . ' --dry-run', $examples);
        }

        return $examples;
    }

    public static function advice(?Command $command): string
    {
        return $command instanceof \Symfony\Component\Console\Command\Command ? 'Пример: ' . self::examples($command)[0] . '. Справка: b24cli ' . $command->getName() . ' --help.' : 'Используйте b24cli list для выбора полного имени команды.';
    }
}
