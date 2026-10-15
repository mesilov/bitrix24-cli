<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Symfony\Component\Console\Command\Command;

final class UsageDiagnostics
{
    public static function parser(\Throwable $exception, ?Command $command): Failure
    {
        $message = $exception->getMessage();
        $options = $command?->getDefinition()->getOptions() ?? [];
        $problem = 'Неверные аргументы или параметры команды.';
        if (preg_match('/^The "(--[a-zA-Z][a-zA-Z0-9-]{0,63})" option (does not exist|requires a value|does not accept a value)\.$/', $message, $match)) {
            $name = substr($match[1], 2);
            if ($match[2] === 'does not exist') {
                $suggestion = $name === 'project' && isset($options['group-id']) ? 'group-id' : self::closest($name, array_keys($options));
                $problem = $suggestion === null ? 'Неизвестный параметр команды.' : 'Неизвестный параметр ' . $match[1] . '. Возможно, нужен --' . $suggestion . '.';
            } elseif (isset($options[$name])) {
                $problem = $match[2] === 'requires a value' ? 'Параметр --' . $name . ' требует значения.' : 'Параметр --' . $name . ' не принимает значение.';
            }
        } elseif (str_starts_with($message, 'Not enough arguments')) {
            $names = array_keys(array_filter($command?->getDefinition()->getArguments() ?? [], static fn ($argument): bool => $argument->isRequired()));
            $problem = 'Отсутствует обязательный аргумент: ' . implode(', ', $names) . '.';
        } elseif (str_starts_with($message, 'Too many arguments') || str_starts_with($message, 'No arguments expected')) {
            $problem = 'Передан лишний позиционный аргумент. Проверьте аргументы и значения параметров.';
        } elseif (preg_match('/^The "-[a-zA-Z]" option does not exist\.$/', $message)) {
            $problem = 'Неизвестный короткий параметр. Используйте полные имена из справки.';
        }

        return Failure::usage($problem . ' ' . TaskUsage::advice($command));
    }

    public static function closest(string $name, array $known): ?string
    {
        if (!preg_match('/^[a-z][a-z0-9:-]{0,63}$/', $name)) {
            return null;
        }

        $distances = [];
        foreach ($known as $candidate) {
            $distance = levenshtein($name, $candidate);
            if ($distance <= 2) {
                $distances[$candidate] = $distance;
            }
        }

        if ($distances === []) {
            return null;
        }

        $minimum = min($distances);
        $best = array_keys($distances, $minimum, true);
        return count($best) === 1 ? $best[0] : null;
    }
}
