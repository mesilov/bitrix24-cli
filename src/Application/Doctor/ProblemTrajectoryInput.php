<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Doctor;

use Bitrix24\CLI\Application\Failure;

final class ProblemTrajectoryInput
{
    public const MAX_REPORT_BYTES = 65536;
    public const MAX_BATCH_BYTES = 524288;
    public const STATES = ['pending', 'analyzed', 'submission-pending', 'submission-unknown', 'submitted', 'mock-submitted', 'linked-existing', 'dismissed'];

    public static function report(array $report): array
    {
        self::keys($report, ['schemaVersion', 'sanitizedScenario', 'availableCommands', 'missingCapability', 'wrapperAlgorithm', 'expectedOutcome', 'cliVersion', 'skillVersion']);
        if (($report['schemaVersion'] ?? null) !== 1) {
            throw Failure::usage('Problem report schemaVersion must be the integer 1.');
        }

        foreach (['sanitizedScenario', 'missingCapability', 'expectedOutcome'] as $field) {
            self::text($report[$field] ?? null);
        }

        self::strings($report['wrapperAlgorithm'] ?? null, 1, 20);
        $commands = $report['availableCommands'] ?? null;
        if (!is_array($commands) || !array_is_list($commands) || count($commands) > 50) {
            throw Failure::usage('availableCommands must be a list of at most 50 command or flag names.');
        }

        foreach ($commands as $command) {
            if (!is_string($command) || preg_match('/^(?:[a-z][a-z0-9]*(?:[-:][a-z0-9]+)*|--[a-z][a-z0-9-]*)$/D', $command) !== 1 || strlen($command) > 128) {
                throw Failure::usage('availableCommands accepts technical names only, without arguments or values.');
            }

            self::text($command, 128);
        }

        foreach (['cliVersion', 'skillVersion'] as $field) {
            if (!array_key_exists($field, $report) || ($report[$field] === null && $field === 'skillVersion')) {
                if ($field === 'skillVersion' && array_key_exists($field, $report)) {
                    continue;
                }

                throw Failure::usage('Report version fields are required; unknown skillVersion is null.');
            }

            self::identifier($report[$field], 128, true);
        }

        return $report;
    }

    public static function batch(array $batch): array
    {
        self::keys($batch, ['schemaVersion', 'batchId', 'groups']);
        if (($batch['schemaVersion'] ?? null) !== 1) {
            throw Failure::usage('Analysis batch schemaVersion must be the integer 1.');
        }

        self::identifier($batch['batchId'] ?? null);
        $groups = $batch['groups'] ?? null;
        if (!is_array($groups) || !array_is_list($groups) || count($groups) < 1 || count($groups) > 100) {
            throw Failure::usage('Analysis batch must contain 1..100 groups.');
        }

        $seenGroups = [];
        $seenCards = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                throw Failure::usage('Each analysis group must be an object.');
            }

            self::keys($group, ['groupId', 'cardIds', 'owner', 'title', 'expected', 'actual', 'syntheticReproduction', 'acceptanceCriteria', 'confidence', 'deduplication', 'decision', 'issueLinks']);
            self::identifier($group['groupId'] ?? null);
            if (in_array($group['groupId'], $seenGroups, true)) {
                throw Failure::usage('Analysis group IDs must be unique.');
            }

            $seenGroups[] = $group['groupId'];
            $ids = $group['cardIds'] ?? null;
            if (!is_array($ids) || !array_is_list($ids) || count($ids) < 1 || count($ids) > 100) {
                throw Failure::usage('Each group must reference 1..100 snapshot card IDs.');
            }

            foreach ($ids as $id) {
                self::cardId($id);
                if (in_array($id, $seenCards, true)) {
                    throw Failure::usage('A snapshot card may belong to one group only.');
                }

                $seenCards[] = $id;
            }

            if (count($seenCards) > 100) {
                throw Failure::usage('One analysis snapshot may contain at most 100 cards.');
            }

            foreach (['title', 'expected', 'actual'] as $field) {
                self::text($group[$field] ?? null, $field === 'title' ? 256 : 4096);
            }

            self::strings($group['syntheticReproduction'] ?? null, 1, 20);
            self::strings($group['acceptanceCriteria'] ?? null, 1, 20);
            foreach (['owner' => ['cli', 'skill/docs', 'environment', 'unclear'], 'confidence' => ['hypothesis', 'confirmed', 'unclear'], 'deduplication' => ['checked', 'unchecked'], 'decision' => ['candidate', 'linked-existing', 'dismissed']] as $field => $values) {
                if (!in_array($group[$field] ?? null, $values, true)) {
                    throw Failure::usage('Invalid analysis classification, deduplication or decision.');
                }
            }

            $links = $group['issueLinks'] ?? null;
            if (!is_array($links) || !array_is_list($links) || count($links) > 10) {
                throw Failure::usage('issueLinks must be a list of at most 10 canonical issues.');
            }

            foreach ($links as $link) {
                if (!is_string($link) || preg_match('~^https://github\.com/mesilov/bitrix24-cli(?:-skill)?/issues/[1-9][0-9]*$~D', $link) !== 1) {
                    throw Failure::usage('Only canonical CLI or skill issue links are accepted.');
                }
            }

            if ($group['decision'] === 'linked-existing' && $links === []) {
                throw Failure::usage('linked-existing requires an existing issue link.');
            }
        }

        return $batch;
    }

    public static function selection(array $selection): array
    {
        self::keys($selection, ['groupRefs']);
        $refs = $selection['groupRefs'] ?? null;
        if (!is_array($refs) || !array_is_list($refs) || count($refs) < 1 || count($refs) > 100 || count(array_unique($refs, SORT_REGULAR)) !== count($refs)) {
            throw Failure::usage('groupRefs must be a unique list of 1..100 saved analysis references.');
        }

        foreach ($refs as $ref) {
            if (!is_string($ref) || preg_match('/^[a-f0-9]{64}$/D', $ref) !== 1) {
                throw Failure::usage('Invalid saved analysis reference.');
            }
        }

        return $refs;
    }

    public static function settings(array $settings): array
    {
        self::keys($settings, ['collectProblemTrajectories', 'maxProblemTrajectoryFiles']);
        if ($settings === []) {
            throw Failure::usage('Provide at least one writable telemetry setting.');
        }

        TelemetrySettings::validate($settings);
        return $settings;
    }

    public static function cardId(mixed $value): void
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{32}$/D', $value) !== 1) {
            throw Failure::usage('Invalid card ID.');
        }
    }

    public static function identifier(mixed $value, int $maximum = 64, bool $version = false): void
    {
        if (!is_string($value) || strlen($value) < 1 || strlen($value) > $maximum || preg_match($version ? '/^[A-Za-z0-9][A-Za-z0-9.+_-]*$/D' : '/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            throw Failure::usage('Use an opaque identifier or version, without paths or personal data.');
        }

        self::text($value, $maximum);
    }

    public static function text(mixed $value, int $maximum = 4096): void
    {
        if (!is_string($value) || trim($value) === '' || strlen($value) > $maximum || !mb_check_encoding($value, 'UTF-8')) {
            throw Failure::usage('Provide bounded nonempty sanitized UTF-8 text.');
        }

        $patterns = [
            '~https?://|/rest/[0-9]+/|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+~i',
            '~(?<![\pL\pN_-])(?:[\pL\pN](?:[\pL\pN-]{0,61}[\pL\pN])?\.)+(?:[\pL]{2,63}|xn--[a-z0-9-]{2,59})(?![\pL\pN_-])~iu',
            '~(?:/Users/|/home/|/private/|/tmp/|/var/|[A-Za-z]:[\\\\/])~i',
            '~\b(?:token|password|secret|authorization|webhook|api[_-]?key)\s*[:=]|\bBearer\s+\S+|\bgh[pousr]_[A-Za-z0-9]+|\bsk-[A-Za-z0-9]+~i',
            '~\+?[0-9][0-9 ()-]{7,}[0-9]|\b(?:taskId|userId|groupId|responsibleId|creatorId)\s*[:=]\s*[0-9]+~i',
            '~```|<\?php|#!|\$\(|\$\{|\b(?:curl|python|bash)\s+-|\b(?:import|subprocess)\s+|[\x00-\x08\x0b\x0c\x0e-\x1f]~i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                throw Failure::usage('Problem text contains a recognizable private value or executable fragment; sanitize it before recording.');
            }
        }

        preg_match_all('~(?<![\pL\pN_])(?:[0-9]{1,3}(?:\.[0-9]{1,3}){3}(?![\pL\pN_.])|[a-f0-9]*:[a-f0-9:.]*:[a-f0-9:.]*(?![\pL\pN_:]))~iu', $value, $addresses);
        foreach ($addresses[0] as $address) {
            if (filter_var(rtrim($address, '.'), FILTER_VALIDATE_IP) !== false) {
                throw Failure::usage('Problem text contains a recognizable private value or executable fragment; sanitize it before recording.');
            }
        }
    }

    private static function strings(mixed $values, int $minimum, int $maximum): void
    {
        if (!is_array($values) || !array_is_list($values) || count($values) < $minimum || count($values) > $maximum) {
            throw Failure::usage('Provide a bounded list of descriptions, not executable code.');
        }

        foreach ($values as $value) {
            self::text($value);
        }
    }

    private static function keys(array $value, array $allowed): void
    {
        if (array_diff(array_keys($value), $allowed) !== []) {
            throw Failure::usage('Unknown problem trajectory input field.');
        }
    }
}
