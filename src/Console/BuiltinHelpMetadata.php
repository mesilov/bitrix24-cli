<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

final class BuiltinHelpMetadata
{
    public static function localize(Command $command): Command
    {
        [$description, $help, $arguments, $options] = match ($command->getName()) {
            'help' => ['Показать справку команды', <<<'HELP'
Команда %command.name% показывает справку выбранной команды:
  b24cli help task:add
Другой формат вывода задаётся через --format (txt, xml, json, md):
  b24cli help task:add --format=xml
Список доступных команд: b24cli list.
HELP, ['command_name' => 'Имя команды.'], ['format' => 'Формат вывода: txt, xml, json или md.', 'raw' => 'Вывести справку без оформления.']],
            'list' => ['Показать список команд', <<<'HELP'
Команда %command.name% показывает все доступные команды:
  b24cli list
Команды выбранного пространства имён:
  b24cli list task
Другой формат вывода задаётся через --format (txt, xml, json, md):
  b24cli list --format=xml
Список без оформления для автоматизации:
  b24cli list --raw
HELP, ['namespace' => 'Пространство имён команд.'], ['raw' => 'Вывести список команд без оформления.', 'format' => 'Формат вывода: txt, xml, json или md.', 'short' => 'Пропустить описание аргументов команд.']],
            'completion' => ['Вывести скрипт автодополнения для оболочки', <<<'HELP'
Команда %command.name% выводит скрипт автодополнения. Оболочки: bash, fish, zsh.
При отсутствии аргумента shell используется переменная окружения SHELL.

Установка из файла (пример для bash):
  b24cli completion bash > completion.sh
  source completion.sh
Чтобы загружать автодополнение при запуске bash, добавьте source с полным путём к файлу в ~/.bashrc.

Динамическая загрузка для bash:
  eval "$(b24cli completion bash)"
Для zsh сохраните скрипт в файл _b24cli в одном из каталогов $fpath и включите compinit.
Для fish сохраните скрипт в ~/.config/fish/completions/b24cli.fish.
HELP, ['shell' => 'Оболочка: bash, fish или zsh; по умолчанию используется SHELL.'], ['debug' => 'Следить за журналом отладки автодополнения.']],
            default => null,
        } ?? [null, null, [], []];
        if ($description === null) {
            return $command;
        }

        $command->setDescription($description)->setHelp($help);
        $definition = $command->getDefinition();
        $localizedArguments = [];
        foreach ($definition->getArguments() as $name => $argument) {
            $mode = ($argument->isRequired() ? InputArgument::REQUIRED : InputArgument::OPTIONAL) | ($argument->isArray() ? InputArgument::IS_ARRAY : 0);
            $localizedArguments[] = new InputArgument($name, $mode, $arguments[$name] ?? $argument->getDescription(), $argument->getDefault(), $argument->hasCompletion() ? static function (CompletionInput $input) use ($argument): array {
                $completionSuggestions = new CompletionSuggestions();
                $argument->complete($input, $completionSuggestions);
                return $completionSuggestions->getValueSuggestions();
            } : []);
        }

        $definition->setArguments($localizedArguments);
        $localizedOptions = [];
        foreach ($definition->getOptions() as $name => $option) {
            $localizedOptions[] = self::option($option, $options[$name] ?? $option->getDescription());
        }

        $definition->setOptions($localizedOptions);
        return $command;
    }

    public static function option(InputOption $option, string $description): InputOption
    {
        $mode = $option->isValueRequired() ? InputOption::VALUE_REQUIRED : ($option->isValueOptional() ? InputOption::VALUE_OPTIONAL : InputOption::VALUE_NONE);
        $mode |= ($option->isArray() ? InputOption::VALUE_IS_ARRAY : 0) | ($option->isNegatable() ? InputOption::VALUE_NEGATABLE : 0);
        return new InputOption($option->getName(), $option->getShortcut(), $mode, $description, $option->acceptValue() || $option->isNegatable() ? $option->getDefault() : null, $option->hasCompletion() ? static function (CompletionInput $input) use ($option): array {
            $completionSuggestions = new CompletionSuggestions();
            $option->complete($input, $completionSuggestions);
            return $completionSuggestions->getValueSuggestions();
        } : []);
    }
}
