<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Application\Task\DeadlineWindow;
use Bitrix24\CLI\Application\Task\DeadlineWindowResolver;
use PHPUnit\Framework\Attributes\Group;

trait DeadlineReadOnlyCases
{
    #[Group('deadline-readonly')]
    public function testReadOnlyWindowsOverdueAndProject(): void
    {
        $command = $this->commandName();
        $control = $this->api->call('tasks.task.list', 1, ['select' => ['ID', 'TITLE', 'DEADLINE', 'STATUS'], 'order' => ['ID' => 'DESC'], 'start' => 0])->result['tasks'];
        $sample = array_find($control, static fn (array $row): bool => is_string($row['deadline'] ?? null) && $row['deadline'] !== '' && is_string($row['title'] ?? null) && trim($row['title']) !== '');
        self::assertNotNull($sample, 'Need an existing deadline task for positive read-only validation.');
        $title = $command === 'task:find' ? ['--title', $sample['title']] : [];
        foreach ([...DeadlineWindowResolver::WINDOWS, null] as $window) {
            foreach ($window === 'this-quarter' ? [false, true] : [$window === null] as $overdue) {
                $args = [$command, ...$title, '--timezone', 'Asia/Bishkek', '--max-scan', '100', '--limit', '10', ...($window === null ? [] : ['--deadline-window', $window]), ...($overdue ? ['--overdue'] : [])];
                $json = $this->invoke($args, [0, 3]);
                $filter = $json['meta']['deadlineFilter'];
                self::assertSame('1.0', $filter['route']['apiVersion']);
                self::assertSame('server', $filter['deadlineApplied']);
                self::assertSame(['1.0'], $json['meta']['apiVersions']);
                $from = $filter['fromInclusive'] === null ? null : new \DateTimeImmutable($filter['fromInclusive']);
                $to = new \DateTimeImmutable($filter['toExclusive']);
                $sampleDate = new \DateTimeImmutable($sample['deadline']);
                if (!$overdue && (!$from instanceof \DateTimeImmutable || $sampleDate >= $from) && $sampleDate < $to) {
                    self::assertNotEmpty($json['data']['items'], 'A window containing an existing task must have a positive result.');
                }

                foreach ($json['data']['items'] as $row) {
                    if ($command === 'task:find') {
                        $row = $this->api->call('tasks.task.get', 3, ['id' => $row['id'], 'select' => ['id', 'deadline', 'status']])->result['item'];
                    }

                    self::assertNotNull($row['deadline']);
                    $date = new \DateTimeImmutable($row['deadline']);
                    self::assertTrue((!$from instanceof \DateTimeImmutable || $date >= $from) && $date < $to);
                    if ($overdue) {
                        self::assertContains($row['status'], DeadlineWindow::OPEN_STATUSES);
                    }
                }

                $this->observedCommands[array_key_last($this->observedCommands)]['deadlineFilter'] = $filter;
                $this->observedCommands[array_key_last($this->observedCommands)]['returned'] = count($json['data']['items']);
            }
        }

        $rows = $this->api->call('tasks.task.list', 1, ['filter' => ['>GROUP_ID' => 0], 'select' => ['ID', 'GROUP_ID', 'DEADLINE', 'STATUS'], 'order' => ['ID' => 'DESC'], 'start' => 0])->result['tasks'];
        $groupId = $rows === [] ? 1 : (int) $rows[0]['groupId'];
        $project = $this->invoke([$command, ...$title, '--overdue', '--timezone', 'Asia/Bishkek', '--group-id', $groupId, '--max-scan', '100', '--limit', '10'], [0, 3]);
        self::assertSame('server', $project['meta']['deadlineFilter']['groupApplied']);
        $this->observedCommands[array_key_last($this->observedCommands)]['projectFixtureAvailable'] = $rows !== [];
        $this->observedCommands[array_key_last($this->observedCommands)]['returned'] = count($project['data']['items']);
        foreach ($project['data']['items'] as $row) {
            if ($command === 'task:find') {
                $row = $this->api->call('tasks.task.list', 3, ['filter' => [['id', '=', $row['id']]], 'select' => ['id', 'groupId']])->result['items'][0];
            }

            self::assertSame($groupId, (int) $row['groupId']);
        }

        $strict = $this->invoke([$command, ...$title, '--deadline-window', 'today', '--api-policy', 'strict-rest3'], [4]);
        self::assertSame([], $strict['meta']['apiCalls']);
        self::assertSame('policy-denied', $strict['error']['code']);
    }
}
