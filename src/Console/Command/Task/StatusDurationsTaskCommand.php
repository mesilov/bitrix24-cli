<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Task\Handler\StatusDurationsTaskHandler;
use Bitrix24\CLI\Application\Task\Request\StatusDurationsTaskRequest;
use Bitrix24\CLI\Console\{CommandRunner, TaskInputMapper};
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:status:durations', description: 'Рассчитать календарное время задачи в статусах')]
final class StatusDurationsTaskCommand extends Command
{
    public function __construct(private readonly StatusDurationsTaskHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner, private readonly Clock $clock)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('as-of', null, InputOption::VALUE_REQUIRED, 'Граница расчёта: ISO 8601 с часовым поясом, не в будущем. По умолчанию — время до чтения API.');
        $this->setHelp('Календарные секунды, включая выходные и завершённый статус; повторные открытия дают отдельные интервалы. Это не трудозатраты. Чтение: REST 1.0 tasks.task.history.list и REST 3.0 tasks.task.get; strict-rest3 отклоняет маршрут до подключения. При недоказанной полноте, неоднозначном начале/порядке или STATUS без подтверждения REAL_STATUS: complete=false, суммы null («неизвестно»), exit 3. elapsedBetweenEventsSeconds — разница меток, не гарантированное время в статусе. JSON: data/meta/error; --plain: status, enteredAt, leftAt, seconds, verified, evidenceIds. Пример: b24cli task:status:durations 123 --as-of 2026-10-01T12:00:00+03:00 --json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): StatusDurationsTaskRequest => StatusDurationsTaskRequest::at($this->mapper->taskId($input), $input->getOption('as-of'), $this->clock->now()),
            fn (): array => $this->handler->routes(),
            fn (StatusDurationsTaskRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
