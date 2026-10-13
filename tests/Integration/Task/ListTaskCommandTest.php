<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use Bitrix24\CLI\Tests\Support\DeadlineReadOnlyCases;
use Bitrix24\CLI\Tests\Support\TaskRoleFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:list')]
final class ListTaskCommandTest extends LiveTaskCommandTestCase
{
    use DeadlineReadOnlyCases;
    use TaskRoleFixtures;

    #[DataProvider('roleCases')]
    public function testRoleIncludesOnlyTheMatchingOwnedFixture(string $role): void
    {
        [$included, $excluded] = $this->roleTaskPair($role, $this->fixtureTitle());
        $result = $this->invoke(['task:list', '--id', $included, '--id', $excluded, '--role', $role]);
        self::assertSame([$included], array_column($result['data']['items'], 'id'));
        self::assertSame(['role' => $role, 'userId' => $this->userId], $result['meta']['roleFilter']);
        self::assertSame(1, $result['meta']['matched']);
    }

    #[Group('happy-path')]
    public function testHappyPathListsSelectedTask(): void
    {
        $title = $this->fixtureTitle();
        $id = $this->createTask($title);
        $result = $this->invoke(['task:list', '--id', $id]);
        self::assertCount(1, $result['data']['items']);
        self::assertSame($id, $result['data']['items'][0]['id']);
        self::assertSame($title, $result['data']['items'][0]['title']);
        self::assertSame($this->userId, $result['data']['items'][0]['responsibleId']);
        self::assertSame('explicit-task-ids', $result['meta']['scope']);
        self::assertTrue($result['meta']['complete']);
    }
}
