<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User\Handler;

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
    public function __construct(private UserPresenceGateway $users, private Clock $clock)
    {
    }

    public function routes(): array
    {
        return [new Route('im.user.list.get', 1)];
    }

    public function prepare(UserPresenceRequest $request): PreparedOperation
    {
        return new PreparedOperation([], function () use ($request): OperationResult {
            $found = $this->users->presence($request->userIds);
            $observedAt = $this->clock->now()->format(\DATE_ATOM);
            $items = [];
            $notFound = [];
            foreach ($request->userIds as $id) {
                if (!isset($found[$id])) {
                    $notFound[] = $id;
                }

                $items[] = isset($found[$id])
                    ? [...$found[$id], 'found' => true, 'observedAt' => $observedAt]
                    : ['id' => $id, 'name' => null, 'lastActivityDate' => null, 'absent' => null, 'found' => false, 'observedAt' => $observedAt];
            }

            return new OperationResult('user-presence', ['items' => $items], ['complete' => true, 'observedAt' => $observedAt, 'requested' => count($request->userIds), 'found' => count($request->userIds) - count($notFound), 'notFound' => $notFound, 'source' => 'im.user.list.get', 'ignoredSignals' => ['status', 'idle', 'IS_ONLINE']]);
        });
    }
}
