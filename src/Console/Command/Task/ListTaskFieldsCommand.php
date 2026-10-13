<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\TaskMetadataService;
use Bitrix24\CLI\Application\Task\Request\ListTaskFieldsRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:fields:list', description: 'Показать поля задачи')]
final class ListTaskFieldsCommand extends Command
{
    public function __construct(private readonly TaskMetadataService $service, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Имя поля метаданных.');
        $this->setHelp('JSON: структура data/meta/error; столбцы TSV при --plain: name, type, editable, filterable, sortable. REST 3.0.   Пример: b24cli task:fields:list --name title');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ListTaskFieldsRequest => $this->mapper->listTaskFields($input),
            fn (ListTaskFieldsRequest $request): array => $this->service->fieldsRoutes($request),
            fn (ListTaskFieldsRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->service->fields($request),
        );
    }
}
