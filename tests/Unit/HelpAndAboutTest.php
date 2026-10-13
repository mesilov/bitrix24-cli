<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Console\ApplicationInformation;
use Bitrix24\CLI\Console\TaskUsage;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\FixturePortal;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;

final class HelpAndAboutTest extends TestCase
{
    public function testRootHelpIsAnOfflineApplicationOverviewAndTracksRegisteredCommands(): void
    {
        $spyConnectionResolver = new SpyConnectionResolver();
        $fixturePortal = new FixturePortal();
        $fakeApiTransport = new FakeApiTransport($fixturePortal->respond(...));
        $application = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $application->addCommand(new Command('extra:read')->setDescription('Additional public command'));
        $application->addCommand(new Command('extra:hidden')->setHidden(true));

        $long = ConsoleHarness::run($application, ['--help']);
        $short = ConsoleHarness::run($application, ['-h']);
        self::assertSame(0, $long->status, $long->stderr);
        self::assertSame($long->stdout, $short->stdout);
        self::assertStringContainsString(ApplicationInformation::PURPOSE, $long->stdout);
        self::assertStringContainsString('task:add --title', $long->stdout);
        self::assertStringContainsString('--dry-run', $long->stdout);
        self::assertStringContainsString('task:show 123', $long->stdout);
        self::assertStringContainsString('task:find --title', $long->stdout);
        self::assertStringContainsString('--about', $long->stdout);
        self::assertStringContainsString('extra:read — Additional public command', $long->stdout);
        self::assertStringNotContainsString('extra:hidden', $long->stdout);
        self::assertStringNotContainsString('list test', $long->stdout);
        foreach ($application->all() as $name => $command) {
            if (!$command->isHidden()) {
                self::assertStringContainsString('    ' . $name . ' — ', $long->stdout);
            }
        }

        self::assertSame(0, $spyConnectionResolver->reads);
        self::assertSame([], $fakeApiTransport->calls);
    }

    public function testCommandHelpAndListsRetainTheirOwnScopeOnAReusedApplication(): void
    {
        $spyConnectionResolver = new SpyConnectionResolver();
        $application = (new ApplicationFactory())->create(dirname(__DIR__, 2), null, $spyConnectionResolver);
        ConsoleHarness::run($application, ['--help']);
        $direct = ConsoleHarness::run($application, ['task:add', '--help']);
        $explicit = ConsoleHarness::run($application, ['help', 'task:add']);
        self::assertSame(0, $direct->status);
        self::assertSame($direct->stdout, $explicit->stdout);
        self::assertStringContainsString('Создать задачу', $direct->stdout);
        self::assertStringNotContainsString('extra:read', $direct->stdout);
        $listHelp = ConsoleHarness::run($application, ['list', '--help']);
        self::assertSame($listHelp->stdout, ConsoleHarness::run($application, ['help', 'list'])->stdout);
        self::assertStringContainsString('list [options]', $listHelp->stdout);
        self::assertStringNotContainsString(ApplicationInformation::PURPOSE, $listHelp->stdout);
        $implicitList = ConsoleHarness::run($application, []);
        self::assertSame($implicitList->stdout, ConsoleHarness::run($application, ['list'])->stdout);
        self::assertSame(0, ConsoleHarness::run($application, ['list', 'task'])->status);
        self::assertSame(0, $spyConnectionResolver->reads);
    }

    public function testAboutUsesRuntimeVersionAndProjectMetadataWithoutCredentialsOrConfigWrites(): void
    {
        $spyConnectionResolver = new SpyConnectionResolver();
        $application = (new ApplicationFactory())->create(dirname(__DIR__, 2), null, $spyConnectionResolver);
        $configDirectory = sys_get_temp_dir() . '/b24cli-about-' . bin2hex(random_bytes(8));
        foreach (['0.2.0', 'dev-' . str_repeat('a', 40)] as $version) {
            $application->setVersion($version);
            $about = ConsoleHarness::run($application, ['--config-dir', $configDirectory, '--env-file', '/does/not/exist', '--about']);
            self::assertSame(0, $about->status, $about->stderr);
            self::assertStringContainsString('Версия: ' . $version, $about->stdout);
            self::assertStringContainsString($version, ConsoleHarness::run($application, ['--version'])->stdout);
            foreach ([ApplicationInformation::PURPOSE, 'Maksim Mesilov', 'bitrix24-cli-license', 'BSD-3-Clause', ApplicationInformation::REPOSITORY, ApplicationInformation::SKILL] as $text) {
                self::assertStringContainsString($text, $about->stdout);
            }

            self::assertFileDoesNotExist($configDirectory);
        }

        $license = file_get_contents(dirname(__DIR__, 2) . '/LICENSE');
        self::assertStringContainsString(ApplicationInformation::AUTHOR, $license);
        self::assertStringContainsString('bitrix24-cli-license', $license);
        self::assertSame(0, $spyConnectionResolver->reads);
        self::assertSame('', ConsoleHarness::run($application, ['--about', '--quiet'])->stdout);
        self::assertSame('', ConsoleHarness::run($application, ['--help', '--silent'])->stdout);
        foreach ([['task:delete', '123', '--force', '--about'], ['task:add', '--title', 'Example', '--about']] as $argv) {
            self::assertSame(2, ConsoleHarness::run($application, $argv)->status);
        }

        self::assertSame(0, $spyConnectionResolver->reads);
    }

    public function testEveryPublicCommandHasOfflineHelpAndParseableExamples(): void
    {
        $spyConnectionResolver = new SpyConnectionResolver();
        $application = (new ApplicationFactory())->create(dirname(__DIR__, 2), null, $spyConnectionResolver);
        foreach ($application->all() as $name => $command) {
            if ($command->isHidden()) {
                continue;
            }

            $direct = ConsoleHarness::run($application, [$name, '--help']);
            $explicit = ConsoleHarness::run($application, ['help', $name]);
            self::assertSame(0, $direct->status, $name . ': ' . $direct->stderr);
            self::assertSame(0, $explicit->status, $name . ': ' . $explicit->stderr);
            self::assertSame($direct->stdout, $explicit->stdout, $name);
            self::assertNotSame('', $direct->stdout, $name);
            if (!isset(CommandCatalog::all()[$name])) {
                continue;
            }

            self::assertStringContainsString('Примеры:', $direct->stdout, $name);
            foreach (TaskUsage::examples($command) as $example) {
                self::assertStringStartsWith('b24cli ' . $name . ' ', $example . ' ', $name);
                self::assertStringNotContainsString('--help', $example, $name);
                $input = new StringInput(trim(substr($example, strlen('b24cli ' . $name))));
                $input->bind($command->getDefinition());
                $input->validate();
                if ($command->getDefinition()->hasOption('dry-run')) {
                    self::assertTrue($input->getOption('dry-run'), $name);
                }
            }
        }

        self::assertSame(0, $spyConnectionResolver->reads);
    }

    public function testPublishedCommandMapMatchesPublicNamesAndDocumentedExamples(): void
    {
        $application = (new ApplicationFactory())->create(dirname(__DIR__, 2));
        $map = file_get_contents(dirname(__DIR__, 2) . '/docs/cli-command-map.md');
        preg_match_all('/^\| (?:\[)?`([^`]+)`/m', $map, $matches);
        $names = array_keys(array_filter($application->all(), static fn (Command $command): bool => !$command->isHidden()));
        sort($names);
        $documented = $matches[1];
        sort($documented);
        self::assertSame($names, $documented);
        foreach (CommandCatalog::all() as $name => $entry) {
            self::assertStringContainsString(TaskUsage::examples($application->get($name))[0], $map, $name);
        }

        $readme = file_get_contents(dirname(__DIR__, 2) . '/README.md');
        self::assertStringContainsString('docs/cli-command-map.md', $readme);
        self::assertStringContainsString(ApplicationInformation::SKILL, $readme);
    }
}
