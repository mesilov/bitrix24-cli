<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:show')]
final class ShowTaskCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReturnsTaskCard(): void
    {
        $title = $this->fixtureTitle();
        $id = $this->createTask($title);
        $result = $this->invoke(['task:show', $id]);
        self::assertSame($id, $result['data']['task']['id']);
        self::assertSame($title, $result['data']['task']['title']);
        self::assertSame('original', $result['data']['task']['description']);
        self::assertSame($this->userId, $result['data']['task']['responsibleId']);
        self::assertSame(['tasks.task.get'], array_column($result['meta']['apiCalls'], 'method'));
        self::assertSame(['3.0'], array_column($result['meta']['apiCalls'], 'apiVersion'));
    }

    public function testSelectedParticipantsAreEnrichedWithEmail(): void
    {
        $id = $this->createTask();
        $this->participants->set($id, [$this->userId], [$this->userId]);
        $result = $this->invoke(['task:show', $id, '--select', 'title', '--select', 'auditors', '--select', 'auditors.email', '--select', 'accomplices']);
        self::assertTrue($result['meta']['complete']);
        self::assertSame(['tasks.task.get', 'tasks.task.get', 'user.get'], array_column($result['meta']['apiCalls'], 'method'));
        self::assertSame(['3.0', '1.0', '1.0'], array_column($result['meta']['apiCalls'], 'apiVersion'));
        self::assertSame([['id' => $this->userId]], array_map(static fn (array $row): array => ['id' => $row['id']], $result['data']['task']['auditors']));
        self::assertSame(array_intersect_key($result['data']['task']['auditors'][0], ['id' => true, 'name' => true]), $result['data']['task']['accomplices'][0]);
        self::assertArrayNotHasKey('email', $result['data']['task']['accomplices'][0]);
        $expected = $this->api->call('user.get', 1, ['filter' => ['@ID' => [$this->userId]], 'select' => ['ID', 'NAME', 'SECOND_NAME', 'LAST_NAME', 'EMAIL']])->result[0];
        self::assertArrayHasKey('email', $result['data']['task']['auditors'][0]);
        self::assertTrue($expected['EMAIL'] === $result['data']['task']['auditors'][0]['email'], 'Observer email must match the selected profile field.');
        self::assertSame(trim(implode(' ', array_filter(array_map(trim(...), [$expected['NAME'], $expected['SECOND_NAME'], $expected['LAST_NAME']]), static fn ($value): bool => $value !== ''))), $result['data']['task']['auditors'][0]['name']);
    }

    public function testParticipantIdsDoNotNeedUserLookup(): void
    {
        $id = $this->createTask();
        $this->participants->set($id, null, [$this->userId]);
        $result = $this->invoke(['task:show', $id, '--select', 'auditors.id']);
        self::assertSame([['id' => $this->userId]], $result['data']['task']['auditors']);
        self::assertSame(['3.0', '1.0'], array_column($result['meta']['apiCalls'], 'apiVersion'));
    }

    public function testEmptyParticipantsSkipUserLookup(): void
    {
        $id = $this->createTask();
        $result = $this->invoke(['task:show', $id, '--select', 'auditors', '--select', 'accomplices']);
        self::assertSame([], $result['data']['task']['auditors']);
        self::assertSame([], $result['data']['task']['accomplices']);
        self::assertSame(['tasks.task.get', 'tasks.task.get'], array_column($result['meta']['apiCalls'], 'method'));
    }
}
