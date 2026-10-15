<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Port\TaskTemplateGateway;
use Bitrix24\CLI\Application\Task\Request\TaskTemplateRequest;

final readonly class TaskTemplateService
{
    public function __construct(private TaskTemplateGateway $templates)
    {
    }

    public function routes(string $action): array
    {
        return $action === 'delete'
            ? [new Route('tasks.template.get', 1), new Route('tasks.template.delete', 1, 'delete')]
            : [new Route('tasks.template.' . $action, 1, in_array($action, ['get', 'fields'], true) ? 'read' : 'write')];
    }

    public function prepare(string $action, TaskTemplateRequest $request): PreparedOperation
    {
        $id = $request->templateId;
        if ($action === 'get') {
            return new PreparedOperation([], fn (): OperationResult => new OperationResult('template-card', ['template' => $this->requireTemplate($id)], ['complete' => true]));
        }

        if ($action === 'fields') {
            return new PreparedOperation([], fn (): OperationResult => new OperationResult('template-fields', ['fields' => $this->templates->fields()], ['complete' => true]));
        }

        $target = $id === null ? 'new task template' : 'task template ' . $id;
        if ($action === 'delete') {
            $template = $this->requireTemplate($id);
            $target .= ': ' . ($template['TITLE'] ?? '');
        }

        $step = [...Plan::step(null, 'tasks.template.' . $action, 1, $action === 'delete' ? 'delete' : 'write', $request->fields), 'templateId' => $id];
        return new PreparedOperation([$step], function () use ($action, $request, $id): OperationResult {
            if ($action === 'add') {
                return $this->mutation($this->templates->add($request->fields), $action, $request->fields);
            }

            if ($action === 'update') {
                $this->templates->update($id, $request->fields);
                return $this->mutation($id, $action, $request->fields);
            }

            $this->templates->delete($id);
            try {
                $visible = $this->templates->get($id) !== null;
            } catch (Failure $failure) {
                if ($failure->exitStatus === 130) {
                    throw new Failure('interrupted', 'Interrupted after sending template deletion; inspect the portal before retrying.', 130, [], true);
                }

                $visible = null;
            }

            return new OperationResult('template-mutation', ['operation' => ['templateId' => $id, 'action' => 'delete', 'changedFields' => [], 'confirmed' => false, 'visibleAfter' => $visible]], [
                'complete' => false, 'outcomeUnknown' => true,
                'reason' => 'Template deletion returned false, which does not confirm deletion. Inspect the portal and permissions before retrying; get=false can also mean missing access.',
            ], 3);
        }, $target);
    }

    private function requireTemplate(int $id): array
    {
        return $this->templates->get($id) ?? throw new Failure('template-unavailable', 'The template is missing or is not visible to this connection.', 4);
    }

    private function mutation(int $id, string $action, array $fields): OperationResult
    {
        return new OperationResult('template-mutation', ['operation' => ['templateId' => $id, 'action' => $action, 'changedFields' => array_keys($fields), 'confirmed' => true]], ['complete' => true]);
    }
}
