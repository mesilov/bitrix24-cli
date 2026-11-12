<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

use Bitrix24\CLI\Application\Chat\Port\ChatSearchGateway;
use Bitrix24\CLI\Application\Chat\Request\FindChatsRequest;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\TaskListScanner;
use Bitrix24\CLI\Application\Task\TaskQuery;

/**
 * Finds chat destinations by title in three sources: group chats (im.search.chat.list),
 * project chats (sonet_group.get → sgN) and task chats (task title → task:N).
 *
 * im.search.chat.list does not return project and task chats and also matches participant
 * names, so every source is searched and titles are compared locally. Only exact titles
 * with a confirmed chat can be selected; an incomplete source blocks the selection.
 */
final readonly class ChatFindService
{
    public const PAGE = 50;

    public function __construct(private ChatSearchGateway $search, private ChatDestinationService $destinations, private TaskListScanner $tasks, private RuntimeState $state)
    {
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return [
            new Route('im.search.chat.list', 1),
            new Route('sonet_group.get', 1),
            new Route('tasks.task.list', 3),
            new Route('tasks.task.get', 3),
            new Route('im.dialog.get', 1),
        ];
    }

    public function prepare(FindChatsRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->find($request));
    }

    public function find(FindChatsRequest $request): OperationResult
    {
        $name = $request->name;
        $sources = [];
        $candidates = [];
        foreach (['chat' => $this->groupChats(...), 'project' => $this->projects(...), 'task' => $this->taskTitles(...)] as $source => $search) {
            [$found, $sources[$source]] = $search($name, $request->maxScan);
            $candidates = [...$candidates, ...$found];
        }

        $complete = !array_any($sources, static fn (array $source): bool => !$source['complete']);
        $exact = array_values(array_filter($candidates, static fn (array $candidate): bool => $candidate['exact']));
        $physical = array_unique(array_map(static fn (array $candidate): string => $candidate['writable'] ? 'chat' . $candidate['chatId'] : $candidate['target'], $exact));
        $status = match (true) {
            !$complete => 'incomplete',
            $physical === [] => 'none',
            count($physical) > 1 => 'ambiguous',
            !$exact[0]['writable'] => 'unavailable',
            default => 'unique',
        };

        usort($candidates, static fn (array $a, array $b): int => [!$a['exact'], array_search($a['source'], ['chat', 'project', 'task'], true), $a['target']] <=> [!$b['exact'], array_search($b['source'], ['chat', 'project', 'task'], true), $b['target']]);
        $matched = count($candidates);
        $items = $request->limit === null ? $candidates : array_slice($candidates, 0, $request->limit);
        $reason = $complete ? null : 'source-incomplete';

        return new OperationResult('chat-candidates', [
            'name' => $name,
            'selection' => ['status' => $status, 'target' => $status === 'unique' ? $exact[0]['target'] : null, 'exactMatches' => count($exact)],
            'items' => $items,
        ], [
            'scope' => 'chats-visible-to-connection',
            'complete' => $complete,
            'reason' => $reason,
            'matched' => $matched,
            'returned' => count($items),
            'limitApplied' => count($items) < $matched,
            'sources' => $sources,
        ], $complete ? 0 : 3);
    }

    /**
     * Scans only the group chat source (im.search.chat.list); chats:add reuses it for its duplicate title check.
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>} candidates and source state
     */
    public function groupChats(string $name, int $budget): array
    {
        $offset = 0;
        $seen = [];
        $found = [];
        $participantOnly = 0;
        $reason = null;
        $error = null;
        while (true) {
            $this->state->checkCancellation();
            try {
                $page = $this->search->chats($name, $offset, min(self::PAGE, $budget - count($seen)));
            } catch (Failure $failure) {
                [$reason, $error] = $this->sourceFailure($failure);
                break;
            }

            $progress = 0;
            foreach ($page['items'] as $row) {
                if (isset($seen[$row['id']])) {
                    continue;
                }

                $seen[$row['id']] = true;
                $progress++;
                if (mb_stripos($row['name'], $name) === false) {
                    $participantOnly++;
                    continue;
                }

                $exact = trim($row['name']) === $name;
                $writable = $exact && $row['type'] !== 'private';
                $found[] = $this->candidate('chat', 'chat' . $row['id'], $row['name'], $exact, $writable, $row['id'], ['chatType' => $row['type']], $exact && !$writable ? 'private-dialog' : null);
            }

            $next = $page['next'];
            if ($next === null) {
                break;
            }

            if ($progress === 0 || $next <= $offset) {
                $reason = 'pagination-no-progress';
                break;
            }

            if (count($seen) >= $budget) {
                $reason = 'scan-budget-exhausted';
                break;
            }

            $offset = $next;
        }

        return [$found, $this->source($reason, count($seen), count($found), $error, ['participantOnly' => $participantOnly])];
    }

    /** @return array{0: list<array<string, mixed>>, 1: array<string, mixed>} */
    private function projects(string $name, int $budget): array
    {
        $start = 0;
        $seen = [];
        $found = [];
        $reason = null;
        $error = null;
        while (true) {
            $this->state->checkCancellation();
            try {
                $page = $this->search->groups($name, $start);
            } catch (Failure $failure) {
                [$reason, $error] = $this->sourceFailure($failure);
                break;
            }

            $progress = 0;
            $overflow = false;
            foreach ($page['items'] as $row) {
                if (isset($seen[$row['id']])) {
                    continue;
                }

                if (count($seen) >= $budget) {
                    $overflow = true;
                    continue;
                }

                $seen[$row['id']] = true;
                $progress++;
                if (mb_stripos($row['name'], $name) === false) {
                    continue;
                }

                $exact = trim($row['name']) === $name;
                $found[] = $this->verified('project', $row['id'], $row['name'], $exact, ['groupId' => $row['id'], 'project' => $row['project']]);
            }

            if ($overflow) {
                $reason = 'scan-budget-exhausted';
                break;
            }

            $next = $page['next'];
            if ($next === null) {
                break;
            }

            if ($progress === 0 || $next <= $start) {
                $reason = 'pagination-no-progress';
                break;
            }

            if (count($seen) >= $budget) {
                $reason = 'scan-budget-exhausted';
                break;
            }

            $start = $next;
        }

        return [$found, $this->source($reason, count($seen), count($found), $error)];
    }

    /** @return array{0: list<array<string, mixed>>, 1: array<string, mixed>} */
    private function taskTitles(string $name, int $budget): array
    {
        try {
            $result = $this->tasks->scan(new TaskQuery(limit: null, maxScan: $budget), $name);
        } catch (Failure $failure) {
            [$reason, $error] = $this->sourceFailure($failure);
            return [[], $this->source($reason, 0, 0, $error)];
        }

        $found = [];
        foreach ($result->data['items'] as $row) {
            $exact = trim($row['title']) === $name;
            $found[] = $this->verified('task', $row['id'], $row['title'], $exact, ['taskId' => $row['id']]);
        }

        return [$found, $this->source($result->meta['complete'] ? null : ($result->meta['reason'] ?? 'source-incomplete'), $result->meta['scanned'], count($found), null)];
    }

    /**
     * Exact project and task matches are confirmed like chats:resolve; other matches are
     * listed for context only and are not checked to keep the request count bounded.
     *
     * @return array<string, mixed>
     */
    private function verified(string $kind, int $id, string $title, bool $exact, array $context): array
    {
        $chatTarget = new ChatTarget($kind === 'project' ? ChatTarget::KIND_PROJECT : ChatTarget::KIND_TASK, $id, 'find');
        if (!$exact) {
            return $this->candidate($kind, $chatTarget->target(), $title, false, false, null, $context, 'not-exact');
        }

        try {
            $resolved = $this->destinations->resolve($chatTarget);
        } catch (Failure $failure) {
            if ($failure->exitStatus === 130) {
                throw $failure;
            }

            return $this->candidate($kind, $chatTarget->target(), $title, true, false, null, $context, $failure->errorCode);
        }

        return $this->candidate($kind, $chatTarget->target(), $title, true, true, $resolved['destination']['chatId'], $context, null);
    }

    /** @return array<string, mixed> */
    private function candidate(string $source, string $target, string $name, bool $exact, bool $writable, ?int $chatId, array $context, ?string $reason): array
    {
        return [
            'source' => $source,
            'target' => $target,
            'name' => $name,
            'exact' => $exact,
            'writable' => $writable,
            'chatId' => $chatId,
            'reason' => $reason ?? ($exact ? null : 'not-exact'),
            ...$context,
        ];
    }

    /** @return array{0: string, 1: array{code: string, message: string}} */
    private function sourceFailure(Failure $failure): array
    {
        if ($failure->exitStatus === 130) {
            throw $failure;
        }

        return ['page-error', ['code' => $failure->errorCode, 'message' => $failure->getMessage()]];
    }

    /** @return array<string, mixed> */
    private function source(?string $reason, int $scanned, int $matched, ?array $error, array $extra = []): array
    {
        return ['complete' => $reason === null, 'reason' => $reason, 'scanned' => $scanned, 'matched' => $matched, ...$extra, ...($error === null ? [] : ['error' => $error])];
    }
}
