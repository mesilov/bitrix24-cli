<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\Port\CurrentUserGateway;
use Bitrix24\CLI\Application\Task\TaskListScanner;
use Bitrix24\CLI\Application\Task\TaskQuery;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1ProjectMemberAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskListAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest3TaskAdapter;
use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:handover')]
final class HandoverTasksCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathTransfersOnlyOwnedFixturesInAnOptInIsolatedProject(): void
    {
        $this->transferFixtures(false);
    }

    public function testManagerTransfersBetweenTwoProjectMembers(): void
    {
        $this->transferFixtures(true);
    }

    public function testDeferredSelectionExcludesOwnedFixtureAndPreservesStatus(): void
    {
        $this->transferFixtures(false, 'deferred', true);
    }

    public function testExplicitDeferredTargetAfterAssignment(): void
    {
        $this->transferFixtures(false, 'deferred');
    }

    private function transferFixtures(bool $manager, ?string $targetStatus = null, bool $selective = false): void
    {
        $groupId = filter_var($this->setting('B24CLI_TEST_HANDOVER_GROUP_ID'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($groupId === false) {
            self::markTestSkipped('Handover acceptance needs an explicitly isolated B24CLI_TEST_HANDOVER_GROUP_ID; ordinary project tasks must not be reassigned.');
        }

        [$second, $secondApi] = $this->secondUser();
        $source = $manager ? $second : $this->userId;
        $target = $manager ? $this->userId : $second;
        $members = (new Rest1ProjectMemberAdapter($this->api))->ids($groupId);
        self::assertContains($source, $members);
        self::assertContains($target, $members);
        $current = new readonly class ($this->userId) implements CurrentUserGateway {
            public function __construct(private int $userId)
            {
            }

            public function id(): int
            {
                return $this->userId;
            }
        };
        $taskListScanner = new TaskListScanner($this->tasks, $current, $this->participants, new RuntimeState(), new Rest1TaskListAdapter($this->api));
        $selection = $taskListScanner->scan(new TaskQuery(where: ['responsibleId' => $source, 'groupId' => $groupId], limit: null, openOnly: true));
        if (($selection->meta['complete'] ?? false) !== true || $selection->data['items'] !== []) {
            self::markTestSkipped('The opt-in project must have a verified empty open-task selection for the source before creating fixtures.');
        }

        $owned = [$this->createTask(fields: ['groupId' => $groupId, 'responsibleId' => $source]), $this->createTask(fields: ['groupId' => $groupId, 'responsibleId' => $source])];
        if ($selective) {
            foreach ($owned as $id) {
                $this->invoke(['task:defer', $id]);
            }

            $owned[] = $this->createTask(fields: ['groupId' => $groupId, 'responsibleId' => $source]);
        }

        $expected = $selective ? [$owned[0]] : $owned;
        $before = [];
        foreach ($owned as $id) {
            $before[$id] = $this->card($id, ['description', 'status', 'deadline']);
        }

        $args = ['task:handover', '--group-id', $groupId, '--responsible', $target, ...($manager ? ['--from', $source] : []), ...($targetStatus === null ? [] : ['--target-status', $targetStatus]), ...($selective ? ['--source-status', 'deferred', '--exclude-id', $owned[1]] : [])];
        $plan = $this->invoke([...$args, '--dry-run']);
        $planned = array_values(array_unique(array_column(array_filter($plan['data']['plan']['steps'], static fn (array $step): bool => $step['effect'] === 'write'), 'taskId')));
        sort($planned);
        sort($expected);
        self::assertSame($expected, $planned, 'The dry run must contain only the disposable fixtures.');
        $result = $this->invoke($args);
        self::assertSame(count($expected), $result['meta']['confirmed']);
        foreach ($owned as $id) {
            $card = $this->card($id, ['responsible.id', 'group.id', 'description', 'status', 'deadline']);
            $selected = in_array($id, $expected, true);
            self::assertSame($selected ? $target : $source, $card['responsibleId']);
            self::assertSame($groupId, $card['groupId']);
            self::assertSame($selected && $targetStatus !== null ? $targetStatus : $before[$id]['status'], $card['status']);
            foreach (['description', 'deadline'] as $field) {
                self::assertSame($before[$id][$field], $card[$field]);
            }

            $independent = (new Rest3TaskAdapter($secondApi))->get($id, ['responsible.id']);
            self::assertSame($selected ? $target : $source, $independent['responsibleId']);
        }
    }
}
