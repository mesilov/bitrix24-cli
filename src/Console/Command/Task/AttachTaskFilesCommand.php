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

#[AsCommand(name: 'task:file:attach', description: 'Прикрепить файл Диска или загрузить локальный файл')]
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
        $this->addOption('file', null, InputOption::VALUE_REQUIRED, 'Один локальный файл до 500 MB; PATH без stdin (-) и URL.');
        $this->addOption('folder-id', null, InputOption::VALUE_REQUIRED, 'ID папки Диска для --file; обязателен в локальном режиме.');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Имя загрузки для --file (по умолчанию basename; до 255 символов).');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV: taskId, resourceId, action, changedFields. Два взаимоисключающих режима: --file-id ID (можно повторять, REST3) или --file PATH --folder-id ID (один локальный regular file, REST1 Disk upload → REST3 attach; нужны права disk/tasks). --name задаёт UTF-8 имя до 255 символов без path separators/control characters; default — basename. Default лимит CLI 500 MB (500000000 bytes), maxUploadBytes в --config overrides default в обе стороны; stdin (-), URLs и специальные файлы не поддерживаются. При совпадении имени generateUniqueName=true, перезаписи нет. --dry-run проверяет локальный ввод и показывает план без credentials/API и содержимого файла. strict-rest3 запрещает локальный режим. После partial используйте возвращённый Disk ID с --file-id, проверив цель/права; при outcomeUnknown сначала проверьте папку/привязку. Примеры: b24cli task:file:attach 123 --file-id 50; b24cli task:file:attach 123 --file "./Отчёт.pdf" --folder-id 456 --name "Документ.pdf"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (\Bitrix24\CLI\Application\InvocationContext $context): AttachTaskFilesRequest => $this->mapper->attachTaskFiles($input, $context->maxUploadBytes),
            fn (AttachTaskFilesRequest $request): array => $this->handler->routes($request),
            fn (AttachTaskFilesRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
