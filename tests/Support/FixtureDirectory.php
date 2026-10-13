<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;

final class FixtureDirectory
{
    public array $current = ['ID' => '1', 'NAME' => 'Иван', 'SECOND_NAME' => 'Петрович', 'LAST_NAME' => 'Иванов', 'EMAIL' => 'ivan@example.test', 'UF_DEPARTMENT' => [7]];
    public array $users;
    public array $departments = [7 => ['ID' => '7', 'PARENT' => '0', 'UF_HEAD' => '2']];

    public function __construct()
    {
        $this->users = [$this->current, ['ID' => '2', 'NAME' => 'Анна', 'SECOND_NAME' => '', 'LAST_NAME' => 'Петрова', 'EMAIL' => 'anna@example.test', 'UF_DEPARTMENT' => [7]]];
    }

    public function respond(string $method, int $version, array $parameters): ApiResponse
    {
        if ($version !== 1) {
            throw new \LogicException('Directory routes must use REST1.');
        }

        if ($method === 'user.current') {
            return new ApiResponse($this->current);
        }

        if ($method === 'department.get') {
            return new ApiResponse(isset($this->departments[$parameters['ID']]) ? [$this->departments[$parameters['ID']]] : []);
        }

        if ($method !== 'user.get') {
            throw new \LogicException('Unexpected directory method.');
        }

        $filter = $parameters['FILTER'];
        $users = array_values(array_filter($this->users, static fn (array $row): bool => (!isset($filter['ID']) || (int) $row['ID'] === $filter['ID']) && (!isset($filter['EMAIL']) || strcasecmp($row['EMAIL'] ?? '', $filter['EMAIL']) === 0)));
        $start = $parameters['start'];
        return new ApiResponse(array_slice($users, $start, 50), $start + 50 < count($users) ? $start + 50 : null, count($users));
    }
}
