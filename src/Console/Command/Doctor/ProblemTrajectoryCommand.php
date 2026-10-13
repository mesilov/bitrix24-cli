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
        $this->addOption('action', null, InputOption::VALUE_REQUIRED, 'record, status, list, configure, record-offer, record-analysis, submit, reconcile, recover.', 'record')
            ->addOption('report-file', null, InputOption::VALUE_REQUIRED, 'Sanitized report JSON file; - reads redirected stdin (64 KiB maximum).')
            ->addOption('settings-file', null, InputOption::VALUE_REQUIRED, 'Writable telemetry JSON settings file or stdin (-).')
            ->addOption('batch-file', null, InputOption::VALUE_REQUIRED, 'Analysis batch or saved group selection JSON file or stdin (-); 512 KiB maximum.')
            ->addOption('idempotency-key', null, InputOption::VALUE_REQUIRED, 'Opaque registration retry key; never use personal IDs.')
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'Card state filter for list; all includes completed cards.', 'pending')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum list cards (1..100).', '100')
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'List offset; snapshot cardIds before analysis.', '0')
            ->addOption('confirm-publication', null, InputOption::VALUE_NONE, 'Explicit consent for the selected, reviewed publication text.')
            ->addOption('mock-feedback', null, InputOption::VALUE_NONE, 'Offline feedback rehearsal: no network, no real receipt or GitHub issue.')
            ->setHelp('Карточки находятся в .problem-trajectories рядом с user config.json (--config-dir / B24CLI_CONFIG_DIR / OS default). По умолчанию локальный сбор включён, лимит 100. Status/list/help ничего не записывают и не читают credentials. Несколько обычных CLI вызовов не являются capability gap. Report содержит schemaVersion=1, sanitizedScenario, availableCommands (имена без значений), missingCapability, wrapperAlgorithm (описания, не код), expectedOutcome, cliVersion и nullable skillVersion. Секреты/контакты/домены/пути/исходные данные портала запрещены. Разбор выполняет скилл; record-analysis сохраняет snapshot и готовый preview. Submit пока доступен только с --mock-feedback и --confirm-publication; mocked=true/delivered=false не означает внешнюю доставку. При interrupted journal используйте --action recover. Полный контракт: docs/problem-trajectories.md.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($input, $output);
    }
}
