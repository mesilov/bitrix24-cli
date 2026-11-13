<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\UpdateTaskHandler;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:deadline:set', description: 'Изменить срок задачи')]
final class SetTaskDeadlineCommand extends Command
{
    public function __construct(private readonly UpdateTaskHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('at', null, InputOption::VALUE_REQUIRED, 'Новый точный срок, ISO8601 с часовым поясом.');
        $this->addOption('day', null, InputOption::VALUE_REQUIRED, 'Относительный день: today, tomorrow, day-after-tomorrow, monday…sunday (ближайший после сегодня), next-week-monday…next-week-sunday или YYYY-MM-DD; требует --time.', suggestedValues: \Bitrix24\CLI\Application\Task\TaskCalendar::dayTokens());
        $this->addOption('time', null, InputOption::VALUE_REQUIRED, 'Время срока HH:MM (00:00-23:59) для --day; часа по умолчанию нет.');
        $this->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'Часовой пояс IANA для --day; по умолчанию TZ или часовой пояс окружения PHP.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: структура data/meta/error; столбцы TSV при --plain: taskId, resourceId, action, changedFields. REST 3.0. Задайте либо точный --at, либо относительный --day с обязательным --time: friday — ближайшая пятница строго после сегодня, next-week-friday — пятница следующей календарной недели. CLI фиксирует один now, вычисляет ISO8601 с offset выбранного --timezone и отклоняет срок не позже now, а также время, пропущенное или повторённое переходом DST; meta.deadlineResolution показывает day, time, timezone, now и deadline. Пример: b24cli task:deadline:set 123 --at 2026-12-01T12:00:00+06:00; b24cli task:deadline:set 123 --day friday --time 17:00 --timezone Europe/Moscow --dry-run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): UpdateTaskRequest => $this->mapper->setTaskDeadline($input),
            fn (UpdateTaskRequest $request): array => $this->handler->routes($request),
            fn (UpdateTaskRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
