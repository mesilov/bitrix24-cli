<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Symfony\Component\Console\Command\Command;

final class TaskUsage
{
    public static function examples(Command $command): array
    {
        return match ($command->getName()) {
            'task:add' => ['b24cli task:add --title "Подготовить договор" --creator 1 --responsible 42'],
            'task:show' => ['b24cli task:show 123'],
            'task:list' => ['b24cli task:list --group-id 7 --responsible 42'],
            'task:find' => ['b24cli task:find --title "договор" --group-id 7 --responsible 42', 'b24cli task:find --title "договор" --where-file filters.json --role responsible --all --json'],
            'task:update' => ['b24cli task:update 123 --title "Договор для согласования"', 'b24cli task:update 123 --responsible 42', 'b24cli task:update 123 --description "Проверить реквизиты"'],
            'task:delete' => ['b24cli task:delete 123 --dry-run'],
            default => preg_match('/Пример(?:ы)?:\s*(b24cli[^;\n]*)/u', $command->getHelp(), $match) ? [trim($match[1])] : ['b24cli ' . $command->getName() . ' --help'],
        };
    }

    public static function advice(?Command $command): string
    {
        return $command instanceof \Symfony\Component\Console\Command\Command ? 'Пример: ' . self::examples($command)[0] . '. Справка: b24cli ' . $command->getName() . ' --help.' : 'Используйте b24cli list для выбора полного имени команды.';
    }
}
