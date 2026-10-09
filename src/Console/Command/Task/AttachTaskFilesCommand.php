<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\AttachTaskFilesHandler;
use Bitrix24\CLI\Application\Task\Request\AttachTaskFilesRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:file:attach', description: 'Прикрепить существующий файл Диска')]
final class AttachTaskFilesCommand extends Command
{
    public function __construct(private readonly AttachTaskFilesHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('file-id', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'ID уже загруженного Disk файла; флаг можно повторять.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: taskId, resourceId, action, changedFields. REST 3.0. Один Disk ID на шаг; локальные файлы не загружаются. Принимаются только существующие Disk file IDs. Каждое вложение — отдельный шаг; неявного upload нет. Пример: b24cli task:file:attach 123 --file-id 50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:file:attach',
            'write',
            fn (): AttachTaskFilesRequest => $this->mapper->attachTaskFiles($input),
            fn (AttachTaskFilesRequest $request): array => $this->handler->routes(),
            fn (AttachTaskFilesRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
