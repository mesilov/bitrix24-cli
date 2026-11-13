<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\InvocationContext;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport;
use Bitrix24\CLI\Infrastructure\Bitrix24\ScopeAwareTransport;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionManager;
use Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore;
use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;
use Bitrix24\CLI\Infrastructure\Connection\WebhookOwnerReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Credentials and portal selection: auth:login, auth:list, auth:switch and auth:logout.
 * Only auth:login without --skip-check talks to the portal; the other commands stay local.
 */
final readonly class AuthCommandRunner
{
    private const MAX_WEBHOOK_BYTES = 1048576;

    public function __construct(private ConnectionManager $connections, private ProfileRepository $profiles, private CommandRunner $runner, private InputSourceReader $reader, private ResultPresenter $presenter, private ApiPolicyGuard $guard, private RuntimeState $state, private ApiTransport $transport, private WebhookOwnerReader $owners)
    {
    }

    public function run(Command $command, InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $command->getName();
        if ((CommandCatalog::LOCAL_COMMANDS[$name]['class'] ?? null) !== $command::class) {
            throw new Failure('configuration-error', 'Local command is absent from the catalog.');
        }

        if ($name === 'auth:login' && $input->getOption('webhook-stdin') && $input->getOption('config') === '-') {
            throw Failure::usage('Webhook input and runtime config cannot both consume stdin.');
        }

        if ($name !== 'auth:list' && ($input->getOption('profile') !== null || $input->getOption('env-file') !== null)) {
            throw Failure::usage('This command addresses the profile by NAME; --profile and --env-file do not apply.');
        }

        $this->reader->reset($input);
        $context = $this->runner->context($input);
        $alias = $input->getArgument('NAME');
        if ($alias !== null) {
            ProfileRepository::alias($alias);
        }

        $directory = $this->connections->directory();
        $result = match ($name) {
            'auth:login' => $this->login($directory, $alias, $context, $input, $output),
            'auth:list' => $this->listing($directory, $alias),
            'auth:switch' => $this->switch($directory, $alias),
            'auth:logout' => $this->logout($directory, $alias),
            default => throw new Failure('configuration-error', 'Unknown local auth command.'),
        };
        $this->presenter->success($result, $context->outputMode, $output, $name, $this->state->calls);
        return 0;
    }

    private function login(string $directory, string $alias, InvocationContext $context, InputInterface $input, OutputInterface $output): OperationResult
    {
        $replace = (bool) $input->getOption('replace');
        $verify = !$input->getOption('skip-check');
        // Local preconditions are settled before the secret is requested or read.
        $this->profiles->assertCanLogin($directory, $alias, $replace);
        if ($verify) {
            try {
                $this->guard->validate('auth:login', [new Route('profile', 1), new Route('scope', 1)], $context->policy);
            } catch (Failure $failure) {
                throw $failure->errorCode === 'policy-denied'
                    ? new Failure('policy-denied', 'auth:login verifies the webhook through REST 1.0 profile and scope, which strict-rest3 prohibits. Use --skip-check to save the profile without verification.', 4)
                    : $failure;
            }
        }

        $webhook = $this->secret($input, $output);
        $hostname = FileCredentialStore::hostname($webhook);
        $owner = null;
        $remember = null;
        if ($verify) {
            $this->state->begin($context->timeout);
            $this->connections->candidate($alias, $webhook);
            $owner = $this->owner();
            $transport = $this->transport;
            if ($transport instanceof ScopeAwareTransport) {
                // Scopes are read before the profile exists; the snapshot is stored only after it is saved.
                $snapshot = $transport->probeScopes();
                $remember = static fn (): array => $transport->rememberScopes($snapshot);
            }
        }

        $saved = $this->profiles->login($directory, $alias, $webhook, $replace);
        $meta = ['local' => !$verify];
        $notices = [$replace
            ? 'Вебхук профиля ' . $alias . ' заменён; настройки профиля сохранены.'
            : 'Профиль ' . $alias . ' сохранён для портала ' . $hostname . '.'];
        if ($replace && $saved['previousHostname'] !== $hostname) {
            $notices[] = 'Hostname портала изменён: ' . $saved['previousHostname'] . ' → ' . $hostname . '. Значения default.* не очищались; проверьте их через config:list --profile ' . $alias . '.';
        }

        if ($saved['becameDefault']) {
            $notices[] = 'Профиль ' . $alias . ' выбран по умолчанию.';
        } elseif (!$saved['isDefault']) {
            $notices[] = 'Профиль по умолчанию не изменён. Чтобы выбрать этот профиль: b24cli auth:switch ' . $alias . '.';
        }

        if ($owner !== null) {
            $notices[] = 'Владелец вебхука: ID ' . $owner['ID'] . ', ' . trim($owner['NAME'] . ' ' . $owner['LAST_NAME']) . ($owner['ADMIN'] === true ? ' (администратор)' : '') . '.';
        }

        if ($verify) {
            $report = $remember instanceof \Closure
                ? $remember()
                : ['status' => 'unknown', 'grantedScopes' => [], 'lastSuccessfulCheckAt' => null, 'reason' => 'The selected API transport does not support connection scope diagnostics.'];
            $meta['scopeValidation'] = $report;
            if ($report['status'] !== 'fresh') {
                $notices[] = 'Права вебхука прочитать не удалось; профиль сохранён. Повторите проверку: b24cli auth:status ' . $alias . '.';
            } elseif ($report['missingScopes'] !== []) {
                $affected = [];
                foreach ($report['potentiallyAffectedCommands'] as $command => $scopes) {
                    $affected[] = $command . ' (' . implode(', ', $scopes) . ')';
                }

                $notices[] = 'Вебхуку не хватает прав: ' . implode(', ', $report['missingScopes']) . '. Профиль сохранён; затронутые команды: ' . implode(', ', $affected) . '.';
                $notices[] = 'Добавьте права в настройках входящего вебхука и проверьте: b24cli auth:status ' . $alias . '.';
            }
        } else {
            $notices[] = 'Вебхук сохранён без проверки (--skip-check). Проверка на портале: b24cli auth:status ' . $alias . '.';
        }

        return new OperationResult('auth-profile', [
            'profile' => $alias, 'name' => $saved['profile']['name'], 'hostname' => $hostname,
            'isDefault' => $saved['isDefault'], 'becameDefault' => $saved['becameDefault'],
            'replaced' => $replace, 'previousHostname' => $saved['previousHostname'],
            'verified' => $verify, 'owner' => $owner,
        ], [...$meta, 'notices' => $notices]);
    }

    private function owner(): array
    {
        try {
            return $this->owners->read($this->transport);
        } catch (Failure $failure) {
            if ($failure->exitStatus === 130) {
                throw $failure;
            }

            throw new Failure($failure->errorCode, 'Webhook verification failed, so the profile was not saved: ' . $failure->getMessage() . ' Check the webhook URL and its user, or use --skip-check to save the profile without verification.', 1, $failure->details);
        }
    }

    private function secret(InputInterface $input, OutputInterface $output): string
    {
        if ($input->getOption('webhook-stdin')) {
            $webhook = $this->reader->read('-', self::MAX_WEBHOOK_BYTES);
        } else {
            if (!$input->isInteractive() || !defined('STDIN') || !stream_isatty(STDIN)) {
                throw Failure::usage('Use --webhook-stdin with redirected input, or run auth:login in an interactive terminal.');
            }

            $question = new Question('HTTPS incoming webhook: ');
            $question->setHidden(true)->setHiddenFallback(false);
            $webhook = (new QuestionHelper())->ask($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output, $question);
        }

        if (!is_string($webhook)) {
            throw Failure::usage('Provide a webhook through hidden input or --webhook-stdin.');
        }

        return trim($webhook);
    }

    private function listing(string $directory, ?string $alias): OperationResult
    {
        $config = $this->profiles->load($directory);
        if ($alias !== null) {
            $this->profiles->profile($directory, $alias);
        }

        $items = [];
        foreach ($config['profiles'] as $name => $profile) {
            if ($alias === null || $alias === $name) {
                $items[] = $this->row($name, $profile, $name === $config['defaultProfile']);
            }
        }

        try {
            $effective = $this->connections->effective();
        } catch (Failure $failure) {
            // A broken selector must not hide the saved profiles; the block reports why the source is unresolved.
            $effective = ['source' => 'unresolved', 'hostname' => null, 'reason' => $failure->getMessage()];
        }

        $notices = [];
        if ($config['profiles'] === []) {
            $notices[] = 'Сохранённых профилей нет. Создайте первый: b24cli auth:login NAME.';
        }

        $notices[] = match ($effective['source']) {
            'none' => 'Фактическое подключение: не настроено.',
            'unresolved' => 'Фактическое подключение: не определено. ' . $effective['reason'],
            default => 'Фактическое подключение: источник ' . $effective['source']
                . (isset($effective['profile']) ? ', профиль ' . $effective['profile'] : '')
                . (isset($effective['variable']) ? ', переменная ' . $effective['variable'] : '')
                . (isset($effective['path']) ? ', путь ' . $effective['path'] : '')
                . ', hostname ' . $effective['hostname'] . '.',
        };
        return new OperationResult('auth-profiles', ['items' => $items, 'effective' => $effective], ['local' => true, 'notices' => $notices]);
    }

    private function switch(string $directory, string $alias): OperationResult
    {
        $profile = $this->profiles->use($directory, $alias);
        return new OperationResult('auth-profile', $this->row($alias, $profile, true), ['local' => true, 'notices' => ['Профиль по умолчанию: ' . $alias . '.']]);
    }

    private function logout(string $directory, string $alias): OperationResult
    {
        $removed = $this->profiles->remove($directory, $alias);
        $notices = ['Профиль ' . $alias . ' и его локальные учётные данные удалены. Вебхук на портале не отозван: удалите его в настройках портала.'];
        if ($removed['wasDefault']) {
            $notices[] = 'Профиль по умолчанию больше не выбран. Выберите другой: b24cli auth:switch NAME.';
        }

        return new OperationResult('auth-profile', [...$this->row($alias, $removed['profile'], false), 'wasDefault' => $removed['wasDefault'], 'portalWebhookRevoked' => false], ['local' => true, 'notices' => $notices]);
    }

    private function row(string $alias, array $profile, bool $default): array
    {
        return ['profile' => $alias, 'name' => $profile['name'], 'hostname' => $profile['connection']['hostname'], 'isDefault' => $default];
    }
}
