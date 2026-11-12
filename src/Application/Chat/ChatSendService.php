<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

use Bitrix24\CLI\Application\Chat\Port\ChatDialogGateway;
use Bitrix24\CLI\Application\Chat\Request\FindChatsRequest;
use Bitrix24\CLI\Application\Chat\Request\SendChatMessageRequest;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Plan;

/**
 * Sends one text message to a verified chat destination.
 *
 * The destination is resolved before any write: a typed TARGET is confirmed like
 * chats:resolve, a --name needs exactly one exact title in a complete search. Dry-run
 * stops after these reads. im.message.add is called once; a lost response is reported as
 * outcomeUnknown and never retried. The returned ID is read back to check its chat.
 */
final readonly class ChatSendService
{
    public function __construct(private ChatDestinationService $destinations, private ChatFindService $finder, private ChatDialogGateway $dialogs)
    {
    }

    /** @return list<Route> */
    public function routes(SendChatMessageRequest $request): array
    {
        $resolve = $request->target instanceof ChatTarget ? $this->destinations->routes($request->target) : $this->finder->routes();
        return [...$resolve, new Route('im.message.add', 1, 'write'), new Route('im.dialog.messages.get', 1)];
    }

    public function prepare(SendChatMessageRequest $request): PreparedOperation
    {
        $target = $request->target ?? $this->select((string) $request->name, $request->maxScan);
        $resolved = $this->destinations->resolve($target);
        $destination = $resolved['destination'];
        $chatId = (int) $destination['chatId'];
        $plan = [
            Plan::step(null, 'im.message.add', 1, 'write', ['target' => $destination['target'], 'dialogId' => 'chat' . $chatId, 'chatName' => $resolved['dialog']['name'] ?? null, 'text' => $request->text]),
            Plan::step(null, 'im.dialog.messages.get', 1, 'read', ['dialogId' => 'chat' . $chatId, 'readback' => 'LAST_ID = messageId + 1, LIMIT 1'], 2),
        ];

        return new PreparedOperation($plan, fn (): OperationResult => $this->send($destination, $chatId, $request->text), $destination['target'] . ' (chat' . $chatId . ')');
    }

    private function select(string $name, int $maxScan): ChatTarget
    {
        $found = $this->finder->find(new FindChatsRequest($name, $maxScan, null));
        $selection = $found->data['selection'];
        $details = [
            'name' => $name,
            'selection' => $selection,
            'candidates' => array_values(array_filter($found->data['items'], static fn (array $item): bool => $item['exact'])),
            'sources' => $found->meta['sources'],
        ];

        if ($selection['status'] !== 'unique') {
            throw match ($selection['status']) {
                'none' => new Failure('chat-not-found', 'No chat, project or task has exactly this title; nothing was sent.', 1, $details),
                'unavailable' => new Failure('chat-unavailable', 'The only exact match has no accessible chat; nothing was sent.', 1, $details),
                'ambiguous' => new Failure('chat-ambiguous', 'Several chats match this title exactly; pass TARGET from data.candidates. Nothing was sent.', 4, $details),
                default => new Failure('search-incomplete', 'The title search is incomplete, so the match is not proven unique; pass TARGET or raise --max-scan. Nothing was sent.', 4, $details),
            };
        }

        [$kind, $id] = match (true) {
            str_starts_with((string) $selection['target'], 'task:') => [ChatTarget::KIND_TASK, (int) substr((string) $selection['target'], 5)],
            str_starts_with((string) $selection['target'], 'sg') => [ChatTarget::KIND_PROJECT, (int) substr((string) $selection['target'], 2)],
            default => [ChatTarget::KIND_CHAT, (int) substr((string) $selection['target'], 4)],
        };

        return new ChatTarget($kind, $id, 'name');
    }

    /** @param array<string, mixed> $destination */
    private function send(array $destination, int $chatId, string $text): OperationResult
    {
        try {
            $messageId = $this->dialogs->send($chatId, $text);
        } catch (Failure $failure) {
            $details = [...$failure->details, 'target' => $destination['target'], 'chatId' => $chatId];
            if ($failure->outcomeUnknown) {
                throw new Failure($failure->errorCode, $failure->getMessage() . ' The message may have been sent to ' . $destination['target'] . '; check chats:messages:list before sending again.', $failure->exitStatus, $details, true);
            }

            throw new Failure($failure->errorCode, $failure->getMessage(), $failure->exitStatus, $details);
        }

        $readback = $this->readback($chatId, $messageId);
        $verified = $readback['status'] === 'verified';
        return new OperationResult('chat-message-sent', [
            'destination' => $destination,
            'operation' => ['action' => 'send', 'target' => $destination['target'], 'chatId' => $chatId, 'messageId' => $messageId, 'confirmed' => true, 'readback' => $readback['status']],
            'readback' => $readback,
        ], [
            'complete' => $verified,
            'reason' => $verified ? null : 'readback-unverified',
            'outcomeUnknown' => false,
        ], $verified ? 0 : 3);
    }

    /** @return array{status: string, messageId: int, chatId: ?int, error?: string} */
    private function readback(int $chatId, int $messageId): array
    {
        try {
            $page = $this->dialogs->messages($chatId, $messageId + 1, 1);
        } catch (Failure $failure) {
            if ($failure->exitStatus === 130) {
                throw $failure;
            }

            return ['status' => 'failed', 'messageId' => $messageId, 'chatId' => null, 'error' => $failure->errorCode];
        }

        $row = $page['items'][0] ?? null;
        if (!is_array($row) || ($row['id'] ?? null) !== $messageId) {
            return ['status' => 'missing', 'messageId' => $messageId, 'chatId' => null];
        }

        $rowChat = (int) ($row['chatId'] ?? 0);
        return ['status' => $rowChat === $chatId ? 'verified' : 'chat-mismatch', 'messageId' => $messageId, 'chatId' => $rowChat];
    }
}
