<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Symfony\Component\Console\Command\Command;

final class TaskUsage
{
    public static function examples(Command $command): array
    {
        $examples = match ($command->getName()) {
            'connection:add' => ['b24cli connection:add work --group-id 7 --creator-id 1 --responsible-id 42'],
            'connection:list' => ['b24cli connection:list'],
            'connection:show' => ['b24cli connection:show work'],
            'connection:use' => ['b24cli connection:use work'],
            'connection:remove' => ['b24cli connection:remove work'],
            'users:current' => ['b24cli users:current --json'],
            'users:find' => ['b24cli users:find --name "Иванов Иван" --json'],
            'users:colleagues' => ['b24cli users:colleagues --department-id 7 --json'],
            'users:manager' => ['b24cli users:manager --department-id 7 --json'],
            'task:add' => ['b24cli task:add --title "Подготовить договор" --creator 1 --responsible 42'],
            'task:show' => ['b24cli task:show 123'],
            'task:list' => ['b24cli task:list --group-id 7 --responsible 42'],
            'task:find' => ['b24cli task:find --title "договор" --group-id 7 --responsible 42', 'b24cli task:find --title "договор" --role responsible --all --json'],
            'task:update' => ['b24cli task:update 123 --title "Договор для согласования"', 'b24cli task:update 123 --responsible 42', 'b24cli task:update 123 --description "Проверить реквизиты"'],
            'task:delete' => ['b24cli task:delete 123 --dry-run'],
            default => preg_match('/Пример(?:ы)?:\s*(b24cli[^;\n]*)/u', $command->getHelp(), $match) ? [trim(preg_split('/\.(?:\s|$)/u', $match[1])[0])] : ['b24cli ' . $command->getName() . ' --help'],
        };
        if ($command->getDefinition()->hasOption('dry-run')) {
            return array_map(static fn (string $example): string => str_contains($example, '--dry-run') ? $example : $example . ' --dry-run', $examples);
        }

        return $examples;
    }

    public static function advice(?Command $command): string
    {
        return $command instanceof \Symfony\Component\Console\Command\Command ? 'Пример: ' . self::examples($command)[0] . '. Справка: b24cli ' . $command->getName() . ' --help.' : 'Используйте b24cli list для выбора полного имени команды.';
    }
}
