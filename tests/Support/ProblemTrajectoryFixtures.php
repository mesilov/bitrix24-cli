<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

final class ProblemTrajectoryFixtures
{
    public static function report(): array
    {
        return ['schemaVersion' => 1, 'sanitizedScenario' => 'Find tasks grouped by a missing business criterion', 'availableCommands' => ['task:list', '--json'], 'missingCapability' => 'A native aggregation option', 'wrapperAlgorithm' => ['Read the task list', 'Group synthetic results by the requested criterion'], 'expectedOutcome' => 'A complete grouped task summary', 'cliVersion' => '0.1.0', 'skillVersion' => null];
    }

    public static function batch(array $ids, string $id = 'fixture-batch', string $decision = 'candidate', string $deduplication = 'checked'): array
    {
        return ['schemaVersion' => 1, 'batchId' => $id, 'groups' => [['groupId' => 'aggregation', 'cardIds' => $ids, 'owner' => 'cli', 'title' => 'Support task aggregation', 'expected' => 'Native grouped summary', 'actual' => 'Public CLI returns a list only', 'syntheticReproduction' => ['Create synthetic tasks in different categories', 'Request a grouped summary'], 'acceptanceCriteria' => ['Grouping works through the public CLI'], 'confidence' => count($ids) === 1 ? 'hypothesis' : 'confirmed', 'deduplication' => $deduplication, 'decision' => $decision, 'issueLinks' => $decision === 'linked-existing' ? ['https://github.com/mesilov/bitrix24-cli/issues/96'] : []]]];
    }
}
