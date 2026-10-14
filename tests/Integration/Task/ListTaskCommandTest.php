<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskLifecycleTestCase;
use Bitrix24\CLI\Application\Task\LifecycleAction;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskLifecycleAdapter;
use Bitrix24\CLI\Tests\Support\DeadlineReadOnlyCases;
use Bitrix24\CLI\Tests\Support\TaskRoleFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:list')]
final class ListTaskCommandTest extends LiveTaskLifecycleTestCase
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

    #[Group('lifecycle')]
    public function testReviewAndRenewListsMatchIndependentTaskState(): void
    {
        $id = $this->awaitingReview();
        foreach ([null, LifecycleAction::Disapprove, LifecycleAction::Complete, LifecycleAction::Renew] as $action) {
            if ($action !== null) {
                if ($action === LifecycleAction::Complete) {
                    (new Rest1TaskLifecycleAdapter($this->completingApi()))->transition($id, $action);
                } else {
                    $this->transition($id, $action);
                }
            }

            $status = $this->card($id, ['status'])['status'];
            $selected = $this->invoke(['task:list', '--id', $id, '--status', $status, '--all']);
            self::assertSame([$id], array_column($selected['data']['items'], 'id'));
            $different = $status === 'completed' ? 'supposedly_completed' : 'completed';
            self::assertSame([], $this->invoke(['task:list', '--id', $id, '--status', $different])['data']['items']);
            if ($status === 'supposedly_completed' && $action === LifecycleAction::Complete) {
                $this->transition($id, LifecycleAction::Approve);
                self::assertSame([$id], array_column($this->invoke(['task:list', '--id', $id, '--status', 'completed'])['data']['items'], 'id'));
            }
        }
    }

    #[Group('lifecycle')]
    public function testOwnTasksFilteredByActualLifecycleStatus(): void
    {
        $first = $this->createTask();
        $second = $this->createTask();
        (new Rest1TaskLifecycleAdapter($this->api))->transition($second, LifecycleAction::Complete);
        foreach (['pending' => $first, 'completed' => $second] as $status => $expected) {
            $result = $this->invoke(['task:list', '--id', $first, '--id', $second, '--responsible', $this->userId, '--status', $status, '--all']);
            self::assertSame([$expected], array_column($result['data']['items'], 'id'));
            self::assertTrue($result['meta']['complete']);
        }
    }

    #[Group('lifecycle')]
    public function testProjectAndOtherAssigneeUseAndPredicates(): void
    {
        $group = $this->setting('B24CLI_TEST_GROUP_ID');
        $other = $this->setting('B24CLI_TEST_OTHER_USER_ID');
        if ($group === '' || $other === '') {
            self::markTestSkipped('Project/other-assignee acceptance needs B24CLI_TEST_GROUP_ID and B24CLI_TEST_OTHER_USER_ID.');
        }

        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $group);
        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $other);
        self::assertNotSame($this->userId, (int) $other);
        $own = $this->createTask(fields: ['groupId' => (int) $group]);
        $otherTask = $this->createTask(fields: ['groupId' => (int) $group, 'responsibleId' => (int) $other]);
        $card = $this->card($otherTask, ['responsible.id', 'group.id', 'status']);
        self::assertSame((int) $other, $card['responsibleId'], json_encode($card, JSON_THROW_ON_ERROR));
        self::assertSame((int) $group, $card['groupId'], json_encode($card, JSON_THROW_ON_ERROR));
        $unfiltered = $this->invoke(['task:list', '--id', $otherTask]);
        self::assertSame('pending', $card['status'], json_encode($card, JSON_THROW_ON_ERROR));
        foreach ([
            [['--group-id', $group], [$otherTask, $own]],
            [['--responsible', $other], [$otherTask]],
            [['--group-id', $group, '--responsible', $other], [$otherTask]],
        ] as [$filters, $expected]) {
            $result = $this->invoke(['task:list', '--id', $own, '--id', $otherTask, ...$filters, '--status', 'pending', '--all']);
            self::assertSame($expected, array_column($result['data']['items'], 'id'), json_encode(['unfiltered' => $unfiltered['data'], 'filtered' => $result], JSON_THROW_ON_ERROR));
            self::assertTrue($result['meta']['complete']);
        }
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
    #[Group('portal-acceptance')]
    public function testBoundedScanCannotClaimCompleteFixtureSearch(): void
    {
        $needle = $this->fixtureTitle();
        $first = $this->createTask($needle . '-first');
        $second = $this->createTask($needle . '-second');
        $result = $this->invoke(['task:list', '--id', $first, '--id', $second, '--max-scan', 1], [3]);
        self::assertFalse($result['meta']['complete']);
        self::assertSame('scan-budget-exhausted', $result['meta']['reason']);
        self::assertSame(1, $result['meta']['scanned']);
        self::assertSame([$second], array_column($result['data']['items'], 'id'));
        self::assertSame(['3.0'], $result['meta']['apiVersions']);
    }

}
