<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Connection;

use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Bootstrap\CommandCatalog;

/**
 * Renders the documented webhook scope matrix from the same manifest and route catalog
 * the scope check enforces, so docs/auth-scopes.md cannot drift silently.
 */
final readonly class ScopeMatrix
{
    public const string START = '<!-- scope-matrix:start -->';
    public const string END = '<!-- scope-matrix:end -->';

    /** Stable column order; the first code of a group is the recommended one. */
    private const array GROUP_ORDER = ['task', 'user', 'department', 'im', 'disk', 'sonet_group', 'note'];

    /**
     * Scopes a command needs only for some options or targets. The catalog keeps the union of all
     * modes, so these conditions are maintained by hand; every pair must exist in the manifest.
     */
    public const array CONDITIONS = [
        'task:list' => ['im' => '`--unread`'],
        'task:show' => ['user' => '`--select` с полями участников кроме `id`'],
        'task:file:attach' => ['disk' => 'локальный файл `--file`'],
        'chats:send' => ['task' => '`--name` или TARGET `task:N`', 'sonet_group' => '`--name`'],
        'chats:resolve' => ['task' => 'TARGET `task:N`'],
        'chats:messages:list' => ['task' => 'TARGET `task:N`'],
        'chats:calls:list' => ['task' => 'TARGET `task:N`'],
        'chats:calls:show' => ['task' => 'TARGET `task:N`'],
        'users:presence' => ['task' => 'TARGET `task:N`'],
    ];

    public function __construct(private ScopeManifest $manifest = new ScopeManifest())
    {
    }

    /**
     * @param list<string> $publicCommands names of all public CLI commands
     */
    public function render(array $publicCommands): string
    {
        $this->assertConditions();
        $sections = [];
        $commands = array_keys(CommandCatalog::ROUTES);
        sort($commands);
        foreach ($commands as $command) {
            $sections[explode(':', $command)[0]][] = $this->row($command);
        }

        ksort($sections);
        $lines = [
            self::START,
            '',
            'Ревизия манифеста: `' . $this->manifest->revision() . '`. То же значение `auth:status` показывает в `scopeManifestRevision`. Блок обновляется командой `make scope-matrix`.',
            '',
        ];
        foreach ($sections as $namespace => $rows) {
            $lines[] = '### `' . $namespace . ':*`';
            $lines[] = '';
            $lines[] = '| Команда | Обязательные scope | Только при опциях | Необязательные | REST-маршруты |';
            $lines[] = '| --- | --- | --- | --- | --- |';
            array_push($lines, ...$rows);
            $lines[] = '';
        }

        $local = array_values(array_diff($publicCommands, $commands));
        sort($local);
        $lines[] = '### Без обращения к порталу';
        $lines[] = '';
        $lines[] = 'Эти команды не требуют scope: ' . implode(', ', array_map(static fn (string $name): string => '`' . $name . '`', $local)) . '.';
        $lines[] = '';
        $lines[] = self::END;

        return implode("\n", $lines);
    }

    /**
     * Every scope code the manifest can require or use optionally for the catalog commands.
     *
     * @return list<string>
     */
    public function scopeCodes(): array
    {
        $codes = [];
        foreach (CommandCatalog::ROUTES as $routes) {
            foreach ($this->manifest->required($this->routes($routes)) as $group) {
                array_push($codes, ...$group);
            }
        }

        foreach (ScopeManifest::OPTIONAL_METHODS as [, $scope]) {
            $codes[] = $scope;
        }

        $codes = array_values(array_unique($codes));
        sort($codes);

        return $codes;
    }

    private function row(string $command): string
    {
        $catalog = CommandCatalog::ROUTES[$command];
        $groups = $this->sortedGroups($this->manifest->required($this->routes($catalog)));
        $conditions = self::CONDITIONS[$command] ?? [];
        $required = [];
        $conditional = [];
        foreach ($groups as $group) {
            $label = $this->groupLabel($group);
            if (isset($conditions[$group[0]])) {
                $conditional[] = $label . ' — ' . $conditions[$group[0]];
            } else {
                $required[] = $label;
            }
        }

        $optional = [];
        $routes = [];
        foreach ($catalog as [$method, $version]) {
            $routes['`' . $method . '` (' . $version . '.0)'] = true;
            $entry = ScopeManifest::OPTIONAL_METHODS[$method] ?? null;
            if ($entry !== null && $entry[0] === $version) {
                $optional['`' . $entry[1] . '` — `' . $method . '`'] = true;
            }
        }

        return '| `' . $command . '` | '
            . ($required === [] ? ($conditional === [] ? 'не требуются' : '—') : implode(', ', $required)) . ' | '
            . ($conditional === [] ? '—' : implode('; ', $conditional)) . ' | '
            . ($optional === [] ? '—' : implode('; ', array_keys($optional))) . ' | '
            . implode(', ', array_keys($routes)) . ' |';
    }

    /**
     * @param list<list<string>> $groups
     * @return list<list<string>>
     */
    private function sortedGroups(array $groups): array
    {
        foreach ($groups as $group) {
            if (!in_array($group[0], self::GROUP_ORDER, true)) {
                throw new \LogicException('Scope group "' . implode('|', $group) . '" has no position in the scope matrix.');
            }
        }

        usort($groups, static fn (array $a, array $b): int => array_search($a[0], self::GROUP_ORDER, true) <=> array_search($b[0], self::GROUP_ORDER, true));

        return $groups;
    }

    /**
     * @param list<string> $group
     */
    private function groupLabel(array $group): string
    {
        return implode(' / ', array_map(static fn (string $code): string => '`' . $code . '`', $group));
    }

    /**
     * @param list<array{0: string, 1: int}> $routes
     * @return list<Route>
     */
    private function routes(array $routes): array
    {
        return array_map(static fn (array $route): Route => new Route($route[0], $route[1]), $routes);
    }

    private function assertConditions(): void
    {
        foreach (self::CONDITIONS as $command => $conditions) {
            if (!array_key_exists($command, CommandCatalog::ROUTES)) {
                throw new \LogicException('Scope matrix condition refers to unknown command "' . $command . '".');
            }

            $keys = array_column($this->manifest->required($this->routes(CommandCatalog::ROUTES[$command])), 0);
            foreach (array_keys($conditions) as $scope) {
                if (!in_array($scope, $keys, true)) {
                    throw new \LogicException('Scope matrix condition "' . $command . '" → "' . $scope . '" is not required by the manifest.');
                }
            }
        }
    }
}
