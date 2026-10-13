<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Application\Failure;

abstract class LiveUsersCommandTestCase extends LiveCommandTestCase
{
    protected function currentUser(): array
    {
        try {
            return $this->api->call('user.current', 1, [])->result;
        } catch (Failure $failure) {
            if ($failure->errorCode === 'permission-denied') {
                self::markTestSkipped('The configured webhook lacks user scope for this users scenario.');
            }

            throw $failure;
        }
    }

    protected function currentDepartment(): int
    {
        $ids = $this->currentUser()['UF_DEPARTMENT'] ?? [];
        if ($ids === [] || (int) $ids[0] < 1) {
            self::markTestSkipped('No visible current department membership; user scope or portal organization data is required.');
        }

        return (int) $ids[0];
    }

    protected function userRows(array $filter): array
    {
        try {
            $users = [];
            $start = 0;
            do {
                $page = $this->api->call('user.get', 1, ['FILTER' => $filter, 'start' => $start]);
                array_push($users, ...$page->result);
                $start = $page->next;
                if (count($users) > 1000) {
                    self::markTestSkipped('Live fixture directory exceeds the bounded 1000-record acceptance budget.');
                }
            } while ($start !== null);

            return $users;
        } catch (Failure $failure) {
            if ($failure->errorCode === 'permission-denied') {
                self::markTestSkipped('The configured webhook lacks user scope for directory fixtures.');
            }

            throw $failure;
        }
    }
}
