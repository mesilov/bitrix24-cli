<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Connection\ScopeMatrix;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

final class ScopeMatrixTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return list<string>
     */
    private function publicCommands(): array
    {
        $commands = (new ApplicationFactory())->create($this->root())->all();

        return array_keys(array_filter($commands, static fn (Command $command): bool => !$command->isHidden()));
    }

    private function block(string $document): string
    {
        $start = strpos($document, ScopeMatrix::START);
        $end = strpos($document, ScopeMatrix::END);
        self::assertNotFalse($start, 'docs/auth-scopes.md has no scope matrix start marker');
        self::assertNotFalse($end, 'docs/auth-scopes.md has no scope matrix end marker');

        return substr($document, $start, $end + strlen(ScopeMatrix::END) - $start);
    }

    private function row(string $matrix, string $command): string
    {
        self::assertSame(1, preg_match('/^\| `' . preg_quote($command, '/') . '` \|.*$/m', $matrix, $match), $command);

        return $match[0];
    }

    public function testDocumentedMatrixMatchesTheManifest(): void
    {
        $document = (string) file_get_contents($this->root() . '/docs/auth-scopes.md');
        $expected = (new ScopeMatrix())->render($this->publicCommands());

        self::assertSame($expected, $this->block($document), 'The scope matrix is stale; run make scope-matrix.');
    }

    public function testRenderingIsDeterministicAndCoversEveryCommand(): void
    {
        $commands = $this->publicCommands();
        $matrix = (new ScopeMatrix())->render($commands);
        self::assertSame($matrix, (new ScopeMatrix())->render(array_reverse($commands)));

        foreach (array_keys(CommandCatalog::ROUTES) as $command) {
            $this->row($matrix, $command);
        }

        preg_match('/^Эти команды не требуют scope: (.*)\.$/m', $matrix, $local);
        self::assertNotEmpty($local);
        foreach (['auth:list', 'auth:switch', 'auth:logout', 'config:set', 'help', 'list', 'completion'] as $command) {
            self::assertStringContainsString('`' . $command . '`', $local[1]);
        }

        self::assertStringNotContainsString('`task:list`', $local[1]);
    }

    public function testRowsShowRequiredConditionalAndOptionalScopes(): void
    {
        $matrix = (new ScopeMatrix())->render($this->publicCommands());

        $list = $this->row($matrix, 'task:list');
        self::assertStringStartsWith('| `task:list` | `task` / `tasks` | `im` — `--unread` | — |', $list);
        self::assertStringContainsString('`im.counters.get` (1.0)', $list);

        self::assertStringStartsWith('| `task:file:attach` | `task` / `tasks` | `disk` — локальный файл `--file` | — |', $this->row($matrix, 'task:file:attach'));
        self::assertStringStartsWith('| `task:chat:list` | `task` / `tasks`, `im` | — | `call` — `call.followup.get` |', $this->row($matrix, 'task:chat:list'));
        self::assertStringStartsWith('| `auth:status` | не требуются | — | — | `profile` (1.0), `scope` (1.0) |', $this->row($matrix, 'auth:status'));
        self::assertStringStartsWith('| `users:manager` | `user` / `user_basic` / `user_brief`, `department` |', $this->row($matrix, 'users:manager'));
    }

    public function testEveryConditionRefersToARequiredScope(): void
    {
        $matrix = (new ScopeMatrix())->render($this->publicCommands());

        foreach (ScopeMatrix::CONDITIONS as $command => $conditions) {
            $row = $this->row($matrix, $command);
            foreach ($conditions as $scope => $condition) {
                self::assertStringContainsString('`' . $scope . '`', $row, $command);
                self::assertStringContainsString($condition, $row, $command);
            }
        }
    }

    public function testReadmeListsEveryScopeAndLinksTheMatrix(): void
    {
        $readme = (string) file_get_contents($this->root() . '/README.md');

        foreach ((new ScopeMatrix())->scopeCodes() as $code) {
            self::assertStringContainsString('`' . $code . '`', $readme, $code);
        }

        self::assertStringContainsString('docs/auth-scopes.md#матрица-разрешений', $readme);
        self::assertStringContainsString('docs/auth-scopes.md#если-портал-отказывает-в-доступе', $readme);
    }
}
