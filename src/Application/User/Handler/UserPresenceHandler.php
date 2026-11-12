<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User\Handler;

use Bitrix24\CLI\Application\Chat\ChatTarget;
use Bitrix24\CLI\Application\Chat\ChatDestinationService;
use Bitrix24\CLI\Application\Chat\Port\ChatMembersGateway;
use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\User\Port\UserPresenceGateway;
use Bitrix24\CLI\Application\User\Request\UserPresenceRequest;

/**
 * Last activity and absence of messenger users; messenger status and online flags are not presence evidence.
 */
final readonly class UserPresenceHandler
{
    public function __construct(private UserPresenceGateway $users, private Clock $clock, private ChatDestinationService $destinations, private ChatMembersGateway $members)
    {
    }

    public function routes(UserPresenceRequest $request): array
    {
        return $request->target instanceof ChatTarget
            ? [...$this->destinations->routes($request->target), new Route('im.dialog.users.list', 1), new Route('im.user.list.get', 1)]
            : [new Route('im.user.list.get', 1)];
    }

    public function prepare(UserPresenceRequest $request): PreparedOperation
    {
        return new PreparedOperation([], function () use ($request): OperationResult {
            $source = [];
            $ids = $request->userIds;
            $complete = true;
            if ($request->target instanceof ChatTarget) {
                $resolved = $this->destinations->resolve($request->target);
                $members = $this->members->members((int) $resolved['destination']['chatId'], $request->maxMembers);
                $ids = $members['ids'];
                $complete = $members['complete'];
                $source = ['destination' => $resolved['destination'], 'members' => ['source' => 'im.dialog.users.list', 'complete' => $members['complete'], 'total' => $members['total']]];
            }

            $found = $ids === [] ? [] : $this->users->presence($ids);
            $observedAt = $this->clock->now()->format(\DATE_ATOM);
            $items = [];
            $notFound = [];
            foreach ($ids as $id) {
                if (!isset($found[$id])) {
                    $notFound[] = $id;
                }

                $items[] = isset($found[$id])
                    ? [...$found[$id], 'found' => true, 'observedAt' => $observedAt]
                    : ['id' => $id, 'name' => null, 'lastActivityDate' => null, 'absent' => null, 'found' => false, 'observedAt' => $observedAt];
            }

            return new OperationResult('user-presence', ['items' => $items, ...array_intersect_key($source, ['destination' => true])], ['complete' => $complete, 'reason' => $complete ? null : 'member-budget-exhausted', 'observedAt' => $observedAt, 'requested' => count($ids), 'found' => count($ids) - count($notFound), 'notFound' => $notFound, 'source' => 'im.user.list.get', ...array_intersect_key($source, ['members' => true]), 'ignoredSignals' => ['status', 'idle', 'IS_ONLINE']], $complete ? 0 : 3);
        });
    }
}
