<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Doctor;

use Bitrix24\CLI\Console\DoctorCommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'doctor:problem-trajectory', description: 'Сохранить и разобрать обезличенные карточки нехватки CLI')]
final class ProblemTrajectoryCommand extends Command
{
    public function __construct(private readonly DoctorCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('action', null, InputOption::VALUE_REQUIRED, 'Действие: record, status, list, configure, record-offer, record-analysis, submit, reconcile, recover.', 'record')
            ->addOption('report-file', null, InputOption::VALUE_REQUIRED, 'Обезличенный отчёт в JSON; - читает перенаправленный stdin (не более 64 KiB).')
            ->addOption('settings-file', null, InputOption::VALUE_REQUIRED, 'Записываемые настройки телеметрии в JSON-файле или stdin (-).')
            ->addOption('batch-file', null, InputOption::VALUE_REQUIRED, 'Пакет анализа или сохранённый выбор групп в JSON-файле либо stdin (-); не более 512 KiB.')
            ->addOption('idempotency-key', null, InputOption::VALUE_REQUIRED, 'Произвольный ключ повторной регистрации; не используйте личные ID.')
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'Фильтр состояния карточек для list; all включает завершённые карточки.', 'pending')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Максимум карточек в списке (1..100).', '100')
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Смещение списка; зафиксируйте cardIds перед анализом.', '0')
            ->addOption('confirm-publication', null, InputOption::VALUE_NONE, 'Явное согласие на выбранный и проверенный текст публикации.')
            ->addOption('mock-feedback', null, InputOption::VALUE_NONE, 'Локальная репетиция обратной связи: без сети, настоящего подтверждения доставки и issue GitHub.')
            ->setHelp('Карточки находятся в .problem-trajectories рядом с пользовательским config.json (--config-dir / B24CLI_CONFIG_DIR / каталог по умолчанию в ОС). По умолчанию локальный сбор включён, лимит 100. status/list/help ничего не записывают и не читают учётные данные. Несколько обычных CLI вызовов не являются нехваткой возможностей. Отчёт содержит schemaVersion=1, sanitizedScenario, availableCommands (имена без значений), missingCapability, wrapperAlgorithm (описания, не код), expectedOutcome, cliVersion и skillVersion с допустимым null. Секреты/контакты/домены/пути/исходные данные портала запрещены. Разбор выполняет скилл; record-analysis сохраняет снимок и готовый предпросмотр. submit пока доступен только с --mock-feedback и --confirm-publication; mocked=true/delivered=false не означает внешнюю доставку. При незавершённой операции в журнале используйте --action recover. Полный контракт: docs/problem-trajectories.md.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($input, $output);
    }
}
