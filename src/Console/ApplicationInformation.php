<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;

final class ApplicationInformation
{
    public const PURPOSE = 'CLI для работы с задачами, сотрудниками и подключениями Битрикс24.';
    public const AUTHOR = 'Maksim Mesilov';
    public const LICENSE = 'bitrix24-cli-license (основана на BSD-3-Clause)';
    public const REPOSITORY = 'https://github.com/mesilov/bitrix24-cli';
    public const SKILL = 'https://github.com/mesilov/bitrix24-cli-skill';

    public function about(Application $application, OutputInterface $output): void
    {
        $output->writeln([
            'b24cli — ' . self::PURPOSE,
            'Версия: ' . OutputFormatter::escape($application->getVersion()),
            'Автор: ' . self::AUTHOR,
            'Лицензия: ' . self::LICENSE,
            'Репозиторий: ' . self::REPOSITORY,
            'Скилл: ' . self::SKILL,
        ]);
    }

    public function help(Application $application, OutputInterface $output): void
    {
        $output->writeln([
            '<comment>Назначение:</comment>',
            '  b24cli — ' . self::PURPOSE,
            '',
            '<comment>Использование:</comment>',
            '  b24cli [глобальные опции] COMMAND [аргументы] [опции]',
            '',
            '<comment>Команды:</comment>',
        ]);
        $groups = [];
        foreach ($application->all() as $name => $command) {
            if ($command->isHidden()) {
                continue;
            }

            $group = str_contains($name, ':') ? explode(':', $name, 2)[0] : '';
            $groups[$group][$name] = $command;
        }

        ksort($groups);
        foreach ($groups as $namespace => $commands) {
            ksort($commands);
            $output->writeln('  <comment>' . ($namespace === '' ? 'Общие' : OutputFormatter::escape($namespace)) . '</comment>');
            foreach ($commands as $name => $command) {
                $output->writeln('    ' . OutputFormatter::escape($name) . ' — ' . OutputFormatter::escape($command->getDescription()));
            }
        }

        $output->writeln(['', '<comment>Глобальные опции:</comment>']);
        HelpDescriptors::create()->describe($output, new InputDefinition($application->getDefinition()->getOptions()));
        $output->writeln(['', '', '<comment>Примеры работы с задачами:</comment>']);
        foreach (['task:add', 'task:show', 'task:find'] as $name) {
            $output->writeln('  ' . OutputFormatter::escape(TaskUsage::examples($application->get($name))[0]));
        }

        $output->writeln([
            '',
            '<comment>Справка конкретной команды:</comment>',
            '  b24cli task:add --help',
            '  b24cli help task:add',
            '  b24cli list task',
            '  b24cli --about',
            '',
            'Документация: ' . self::REPOSITORY . '/blob/dev/docs/cli-command-map.md',
        ]);
    }
}
