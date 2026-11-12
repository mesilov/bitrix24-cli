<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

use Bitrix24\CLI\Application\Chat\Port\ChatDialogGateway;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\ChatSupport;

/**
 * Resolves a typed destination to a verified chat. Reads only: im.dialog.get and,
 * for task:N, the existing REST3 task → chat binding. No join, read or create calls.
 */
final readonly class ChatDestinationService
{
    public function __construct(private ChatDialogGateway $dialogs, private ChatSupport $tasks)
    {
    }

    /** @return list<Route> */
    public function routes(ChatTarget $target): array
    {
        return $target->kind === ChatTarget::KIND_TASK
            ? [new Route('tasks.task.get', 3), new Route('im.dialog.get', 1)]
            : [new Route('im.dialog.get', 1)];
    }

    public function prepare(ChatTarget $target): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => new OperationResult('chat-destination', $this->resolve($target), ['complete' => true]), $target->target());
    }

    /** @return array{destination: array<string, mixed>, dialog: array<string, mixed>} */
    public function resolve(ChatTarget $target): array
    {
        $chatId = $target->kind === ChatTarget::KIND_TASK ? $this->tasks->chatId($target->id) : null;
        $dialogId = match ($target->kind) {
            ChatTarget::KIND_CHAT => 'chat' . $target->id,
            ChatTarget::KIND_PROJECT => 'sg' . $target->id,
            default => 'chat' . $chatId,
        };
        $dialog = $this->dialogs->dialog($dialogId);
        $this->verify($target, $dialog, $chatId);

        return [
            'destination' => [
                'kind' => $target->kind,
                'target' => $target->target(),
                'source' => $target->source,
                'chatId' => $dialog['id'],
                'groupId' => $target->kind === ChatTarget::KIND_PROJECT ? $target->id : null,
                'taskId' => $target->kind === ChatTarget::KIND_TASK ? $target->id : null,
                'messageId' => $target->messageId,
            ],
            'dialog' => $dialog,
        ];
    }

    private function verify(ChatTarget $target, array $dialog, ?int $chatId): void
    {
        $verified = match ($target->kind) {
            ChatTarget::KIND_CHAT => $dialog['id'] === $target->id && $dialog['type'] !== 'private',
            ChatTarget::KIND_PROJECT => $dialog['entity_type'] === 'SONET_GROUP' && $dialog['entity_id'] === (string) $target->id,
            default => $dialog['id'] === $chatId && $dialog['entity_type'] === 'TASKS_TASK' && $dialog['entity_id'] === (string) $target->id,
        };
        if (!$verified) {
            throw Failure::binding('The API did not confirm the selected ' . $target->kind . ' chat; check TARGET with chats:resolve.');
        }
    }
}
