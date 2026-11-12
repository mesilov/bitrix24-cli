<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

use Bitrix24\CLI\Application\Chat\Port\ChatCreateGateway;
use Bitrix24\CLI\Application\Chat\Port\ChatDialogGateway;
use Bitrix24\CLI\Application\Chat\Port\ChatMembersGateway;
use Bitrix24\CLI\Application\Chat\Request\CreateChatRequest;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Plan;

/**
 * Creates one group chat (im.chat.add TYPE CHAT or OPEN) for the connection user.
 *
 * Creation is not idempotent and REST cannot delete a chat, so before any write the
 * group chat search must prove that no accessible chat already has exactly this title;
 * a match or an incomplete search blocks the write unless the duplicate is allowed.
 * Dry-run stops after the check. im.chat.add is called once; a lost response is reported
 * as outcomeUnknown and never retried. The new chat is read back by ID to confirm its
 * title and the requested members.
 */
final readonly class ChatCreateService
{
    public const MAX_MEMBERS = 1000;

    public function __construct(private ChatFindService $finder, private ChatCreateGateway $chats, private ChatDialogGateway $dialogs, private ChatMembersGateway $members)
    {
    }

    /** @return list<Route> */
    public function routes(CreateChatRequest $request): array
    {
        return [
            ...($request->maxScan === null ? [] : [new Route('im.search.chat.list', 1)]),
            new Route('im.chat.add', 1, 'write'),
            new Route('im.dialog.get', 1),
            new Route('im.dialog.users.list', 1),
        ];
    }

    public function prepare(CreateChatRequest $request): PreparedOperation
    {
        $check = $this->duplicateCheck($request);
        $fields = $this->fields($request);
        $plan = [
            Plan::step(null, 'im.chat.add', 1, 'write', ['title' => $request->title, 'type' => $request->type, 'description' => $request->description, 'userIds' => $request->userIds, 'duplicateCheck' => $check['status']]),
            Plan::step(null, 'im.dialog.get', 1, 'read', ['readback' => 'DIALOG_ID = chat{created chat ID}: id, name'], 2),
            Plan::step(null, 'im.dialog.users.list', 1, 'read', ['readback' => 'requested userIds are members'], 3),
        ];

        return new PreparedOperation($plan, fn (): OperationResult => $this->create($request, $fields, $check), 'new ' . $request->type . ' chat "' . $request->title . '"');
    }

    /** @return array{status: string, scanned: int} */
    private function duplicateCheck(CreateChatRequest $request): array
    {
        if ($request->maxScan === null) {
            return ['status' => 'skipped', 'scanned' => 0];
        }

        [$found, $source] = $this->finder->groupChats($request->title, $request->maxScan);
        $duplicates = array_values(array_filter($found, static fn (array $candidate): bool => $candidate['exact'] && $candidate['writable']));
        $details = ['title' => $request->title, 'candidates' => $duplicates, 'source' => $source];
        if ($duplicates !== []) {
            throw new Failure('chat-exists', 'An accessible group chat already has exactly this title; send to it via chats:send or pass --allow-duplicate-title. Nothing was created.', 4, $details);
        }

        if (!$source['complete']) {
            throw new Failure('search-incomplete', 'The group chat search is incomplete, so the title is not proven unused; raise --max-scan or pass --allow-duplicate-title. Nothing was created.', 4, $details);
        }

        return ['status' => 'none', 'scanned' => $source['scanned']];
    }

    /** @return array<string, mixed> documented im.chat.add fields */
    private function fields(CreateChatRequest $request): array
    {
        return [
            'TITLE' => $request->title,
            'TYPE' => $request->type,
            ...($request->description === null ? [] : ['DESCRIPTION' => $request->description]),
            ...($request->userIds === [] ? [] : ['USERS' => $request->userIds]),
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @param array{status: string, scanned: int} $check
     */
    private function create(CreateChatRequest $request, array $fields, array $check): OperationResult
    {
        try {
            $chatId = $this->chats->create($fields);
        } catch (Failure $failure) {
            $details = [...$failure->details, 'title' => $request->title];
            if ($failure->outcomeUnknown) {
                throw new Failure($failure->errorCode, $failure->getMessage() . ' The chat may have been created; check chats:find --name before creating it again.', $failure->exitStatus, $details, true);
            }

            throw new Failure($failure->errorCode, $failure->getMessage(), $failure->exitStatus, $details);
        }

        $target = 'chat' . $chatId;
        $readback = $this->readback($chatId, $request);
        $verified = $readback['status'] === 'verified';
        return new OperationResult('chat-created', [
            'chat' => ['target' => $target, 'chatId' => $chatId, 'title' => $request->title, 'type' => $request->type, 'description' => $request->description, 'userIds' => $request->userIds],
            'operation' => ['action' => 'create', 'target' => $target, 'chatId' => $chatId, 'confirmed' => true, 'readback' => $readback['status']],
            'readback' => $readback,
            'duplicateCheck' => $check,
        ], [
            'complete' => $verified,
            'reason' => $verified ? null : 'readback-unverified',
            'outcomeUnknown' => false,
        ], $verified ? 0 : 3);
    }

    /** @return array<string, mixed> */
    private function readback(int $chatId, CreateChatRequest $request): array
    {
        try {
            $dialog = $this->dialogs->dialog('chat' . $chatId);
            $members = $this->members->members($chatId, self::MAX_MEMBERS);
        } catch (Failure $failure) {
            if ($failure->exitStatus === 130) {
                throw $failure;
            }

            return ['status' => 'failed', 'chatId' => $chatId, 'error' => $failure->errorCode];
        }

        $present = array_values(array_intersect($request->userIds, $members['ids']));
        $missing = array_values(array_diff($request->userIds, $members['ids']));
        $status = match (true) {
            $dialog['id'] !== $chatId || !in_array($request->title, [trim($dialog['name']), trim(html_entity_decode($dialog['name'], ENT_QUOTES | ENT_HTML5))], true) => 'title-mismatch',
            $missing !== [] => 'members-missing',
            default => 'verified',
        };

        return [
            'status' => $status,
            'chatId' => $chatId,
            'dialog' => ['id' => $dialog['id'], 'name' => $dialog['name'], 'type' => $dialog['type'], 'owner' => $dialog['owner'] ?? null],
            'members' => ['requested' => $request->userIds, 'present' => $present, 'missing' => $missing, 'complete' => $members['complete']],
        ];
    }
}
