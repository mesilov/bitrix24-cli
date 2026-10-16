<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Connection;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Bootstrap\CommandCatalog;

final class ScopeManifest
{
    // See docs/connection-scopes.md for official sources and the task/tasks discrepancy.
    private const TASK_METHODS = [
        'tasks.template.get', 'tasks.template.fields', 'tasks.template.add', 'tasks.template.update', 'tasks.template.delete',
        'tasks.task.add', 'tasks.task.get', 'tasks.task.list', 'tasks.task.update', 'tasks.task.delete',
        'tasks.task.getFields', 'tasks.task.field.list', 'tasks.task.field.get', 'tasks.task.access.get',
        'tasks.task.history.list', 'tasks.task.file.attach', 'tasks.task.chat.message.send',
        'tasks.task.start', 'tasks.task.pause', 'tasks.task.defer', 'tasks.task.renew',
        'tasks.task.complete', 'tasks.task.approve', 'tasks.task.disapprove',
        'tasks.task.result.add', 'tasks.task.result.list', 'tasks.task.result.update', 'tasks.task.result.delete',
        'task.elapseditem.add', 'task.elapseditem.getlist', 'task.elapseditem.update', 'task.elapseditem.delete',
        'task.checklistitem.add', 'task.checklistitem.getlist', 'task.checklistitem.update',
        'task.checklistitem.complete', 'task.checklistitem.renew', 'task.checklistitem.delete',
    ];

    private const OTHER_METHODS = [
        'sonet_group.user.get' => [['sonet_group']],
        'scope' => [],
        'user.current' => [['user', 'user_basic', 'user_brief']],
        'user.get' => [['user', 'user_basic', 'user_brief']],
        'department.get' => [['department']],
        'im.dialog.messages.get' => [['im']],
        'im.message.update' => [['im']],
        'im.message.delete' => [['im']],
        'disk.folder.uploadFile' => [['disk']],
    ];

    public function revision(): string
    {
        return hash('sha256', json_encode([self::TASK_METHODS, self::OTHER_METHODS, CommandCatalog::ROUTES], JSON_THROW_ON_ERROR));
    }

    public function required(array $routes): array
    {
        $groups = [];
        foreach ($routes as $route) {
            if (!$route instanceof Route || !in_array($route->version, [1, 3], true)) {
                throw new Failure('configuration-error', 'Invalid route in the scope manifest.');
            }

            if (in_array($route->method, self::TASK_METHODS, true)) {
                $required = [['task', 'tasks']];
            } elseif ($route->version === 1 && array_key_exists($route->method, self::OTHER_METHODS)) {
                $required = self::OTHER_METHODS[$route->method];
            } else {
                throw new Failure('configuration-error', 'The API route has no scope requirement in the installed manifest.');
            }

            foreach ($required as $group) {
                $groups[implode('|', $group)] = $group;
            }
        }

        return array_values($groups);
    }

    public function missing(array $required, array $granted): array
    {
        return array_values(array_filter($required, static fn (array $group): bool => array_intersect($group, $granted) === []));
    }

    public function affectedCommands(array $granted): array
    {
        $affected = [];
        foreach (CommandCatalog::ROUTES as $command => $routes) {
            $required = $this->required(array_map(static fn (array $route): Route => new Route($route[0], $route[1]), $routes));
            $missing = $this->missing($required, $granted);
            if ($missing !== []) {
                $affected[$command] = array_column($missing, 0);
            }
        }

        return $affected;
    }
}
