<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\User\Port\UserDirectoryGateway;

final readonly class UserScanner
{
    public function __construct(private UserDirectoryGateway $users, private RuntimeState $state)
    {
    }

    public function scan(UserQuery $query, array $filter, \Closure $matches, array $meta = []): OperationResult
    {
        $items = [];
        $seen = [];
        $unavailable = [];
        $scanned = 0;
        $start = 0;
        $reason = null;
        $status = 0;
        try {
            do {
                $this->state->checkCancellation();
                $page = $this->users->page($filter, $start);
                foreach ($page->items as $user) {
                    $this->state->checkCancellation();
                    if ($scanned >= $query->maxScan) {
                        $reason = 'The directory scan budget was reached.';
                        break 2;
                    }

                    $scanned++;
                    $id = $user['ID'];
                    if (isset($seen[$id])) {
                        throw new Failure('api-error', 'The directory repeated a user ID across pages.');
                    }

                    $seen[$id] = true;
                    foreach (array_diff(UserRecord::FIELDS, array_keys($user)) as $field) {
                        $unavailable[$field] = true;
                    }

                    $match = $matches($user);
                    if ($match === null) {
                        $reason ??= 'Required user fields are unavailable for matching; check user scope.';
                    } elseif ($match) {
                        $items[] = $user;
                    }
                }

                if ($page->next === null && $page->total !== null && $page->total > $start + count($page->items)) {
                    $reason ??= 'The directory omitted pagination before the reported total was reached.';
                }

                if ($page->next !== null && $scanned >= $query->maxScan) {
                    $reason ??= 'The directory scan budget was reached.';
                    break;
                }

                $start = $page->next;
            } while ($start !== null);
        } catch (Failure $failure) {
            if ($scanned === 0) {
                throw $failure;
            }

            $reason = $failure->getMessage();
            $status = $failure->exitStatus === 130 ? 130 : 3;
        }

        $matched = count($items);
        $returned = $query->limit === null ? $items : array_slice($items, 0, $query->limit);
        return new OperationResult('user-list', ['items' => $returned], [...$meta,
            'scope' => 'Active employees visible to the selected connection.', 'unavailableFields' => array_keys($unavailable), 'scanned' => $scanned,
            'matched' => $matched, 'returned' => count($returned), 'limitApplied' => count($returned) < $matched,
            'complete' => $reason === null, ...($reason === null ? [] : ['reason' => $reason]),
        ], $status ?: ($reason === null ? 0 : 3));
    }
}
