<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\User\Port\DepartmentGateway;
use Bitrix24\CLI\Application\User\Port\UserDirectoryGateway;
use Bitrix24\CLI\Application\User\Request\FindUsersRequest;
use Bitrix24\CLI\Application\User\Request\GetManagerRequest;
use Bitrix24\CLI\Application\User\Request\ListColleaguesRequest;

final readonly class UserDirectoryService
{
    public function __construct(private UserDirectoryGateway $users, private DepartmentGateway $departments, private UserScanner $scanner, private RuntimeState $state)
    {
    }

    private function card(?array $user, array $meta = []): OperationResult
    {
        return new OperationResult('user-card', ['user' => $user], ['complete' => true, 'unavailableFields' => $user === null ? [] : array_values(array_diff(UserRecord::FIELDS, array_keys($user))), ...$meta]);
    }

    public function current(): OperationResult
    {
        return $this->card($this->users->current());
    }

    public function find(FindUsersRequest $request): OperationResult
    {
        $filter = ['USER_TYPE' => 'employee', 'ACTIVE' => true];
        if ($request->email !== null) {
            $filter['EMAIL'] = $request->email;
        }

        $tokens = $request->name === null ? [] : preg_split('/\s+/u', mb_strtolower($request->name), -1, PREG_SPLIT_NO_EMPTY);
        return $this->scanner->scan($request->query, $filter, static function (array $user) use ($tokens, $request): ?bool {
            if ($request->email !== null) {
                return isset($user['EMAIL']) ? strcasecmp($user['EMAIL'], $request->email) === 0 : null;
            }

            if (!isset($user['NAME'], $user['LAST_NAME'])) {
                return null;
            }

            $name = mb_strtolower(implode(' ', [$user['NAME'], $user['SECOND_NAME'] ?? '', $user['LAST_NAME']]));
            return array_all($tokens, static fn (string $token): bool => str_contains($name, $token));
        });
    }

    private function department(array $current, ?int $selected): int
    {
        $ids = $current['UF_DEPARTMENT'] ?? [];
        if ($ids === []) {
            throw new Failure('organization-unavailable', 'Current department membership is unavailable; check user scope and organization settings.');
        }

        if ($selected !== null) {
            if (!in_array($selected, $ids, true)) {
                throw Failure::usage('--department-id must be one of the current user departments.');
            }

            return $selected;
        }

        if (count($ids) > 1) {
            throw new Failure('ambiguous-department', 'Choose --department-id from the current user departments: ' . implode(', ', $ids) . '.', 2, ['departments' => $ids]);
        }

        return $ids[0];
    }

    public function colleagues(ListColleaguesRequest $request): OperationResult
    {
        $current = $this->users->current();
        $departmentId = $this->department($current, $request->departmentId);
        return $this->scanner->scan($request->query, ['USER_TYPE' => 'employee', 'ACTIVE' => true, 'UF_DEPARTMENT' => $departmentId], static function (array $user) use ($current, $departmentId): ?bool {
            if ($user['ID'] === $current['ID']) {
                return false;
            }

            return isset($user['UF_DEPARTMENT']) ? in_array($departmentId, $user['UF_DEPARTMENT'], true) : null;
        }, ['departmentId' => $departmentId, 'currentUserId' => $current['ID']]);
    }

    public function manager(GetManagerRequest $request): OperationResult
    {
        if ($request->source === 'config') {
            return $this->managerCard($request->configuredId, ['source' => 'config']);
        }

        $current = $this->users->current();
        $departmentId = $this->department($current, $request->departmentId);
        $original = $departmentId;
        $visited = [];
        $meta = ['source' => 'org', 'currentUserId' => $current['ID'], 'originalDepartmentId' => $original, 'departmentId' => $departmentId, 'departmentChain' => []];
        while ($departmentId !== 0) {
            $this->state->checkCancellation();
            if (isset($visited[$departmentId]) || count($visited) >= 100) {
                throw new Failure('organization-unavailable', 'The organization hierarchy contains a cycle or exceeds 100 departments.');
            }

            $visited[$departmentId] = true;
            $department = $this->departments->get($departmentId);
            $head = $department['UF_HEAD'] ?? 0;
            $meta = ['source' => 'org', 'currentUserId' => $current['ID'], 'originalDepartmentId' => $original, 'departmentId' => $departmentId, 'departmentChain' => array_keys($visited)];
            if ($head !== 0 && $head !== (int) $current['ID']) {
                return $this->managerCard($head, $meta);
            }

            if (!array_key_exists('PARENT', $department)) {
                throw new Failure('organization-unavailable', 'The organization omitted the parent department; manager resolution cannot continue.');
            }

            $departmentId = $department['PARENT'];
        }

        return $this->card(null, [...$meta, 'reason' => 'no-manager']);
    }

    private function managerCard(int $id, array $meta): OperationResult
    {
        $user = $this->users->byId($id);
        if ($user === null) {
            throw new Failure('organization-unavailable', 'The selected manager is unavailable to this connection; verify the configured ID or organization.');
        }

        return $this->card($user, $meta);
    }
}
