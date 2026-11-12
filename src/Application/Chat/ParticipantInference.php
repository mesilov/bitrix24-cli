<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

/**
 * Deterministic attendance estimate, rules version 1 (see the add-chat-call-insights design).
 * Online flags and messenger status are never inputs.
 */
final class ParticipantInference
{
    public const RULES_VERSION = 1;
    private const READ_AT_END_SECONDS = 5;
    private const LEFT_EARLY_SECONDS = 60;
    private const CONFIDENCE = ['low' => 1, 'medium' => 2, 'high' => 3];
    private const GROUPS = [
        'finish-author' => 'end', 'read-finish-at-end' => 'end',
        'call-initiator' => 'during', 'message-during-call' => 'during',
        'last-read-before-start' => 'absence', 'last-activity-before-start' => 'absence',
        'last-activity-before-finish' => 'left',
    ];

    /**
     * @param array{
     *     eligible: list<int>,
     *     startId?: ?int, startAt?: ?string, finishId?: ?int, finishAt?: ?string,
     *     finishAuthorId?: ?int, finishInitiatorId?: ?int, initiatorId?: ?int,
     *     messages?: list<array{id: int, authorId: int, createdDate: string}>,
     *     readers?: array<int, array{messageId: int, date: ?string}>,
     *     lastActivity?: array<int, ?string>,
     *     fresh: bool
     * } $call
     * @return array{users: list<array>, summary: array{present: int, absent: int, leftEarly: int, unknown: int}}
     */
    public static function infer(array $call): array
    {
        $startAt = self::time($call['startAt'] ?? null);
        $finishAt = self::time($call['finishAt'] ?? null);
        $users = [];
        $summary = ['present' => 0, 'absent' => 0, 'leftEarly' => 0, 'unknown' => 0];
        $eligible = array_values(array_unique($call['eligible']));
        sort($eligible);
        foreach ($eligible as $userId) {
            $evidence = self::evidence($userId, $call, $startAt, $finishAt);
            $users[] = $user = ['userId' => $userId, ...self::verdict($evidence), 'evidence' => $evidence];
            $summary[match ($user['verdict']) {
                'present' => 'present',
                'absent' => 'absent',
                'left-early' => 'leftEarly',
                default => 'unknown',
            }]++;
        }

        return ['users' => $users, 'summary' => $summary];
    }

    private static function evidence(int $userId, array $call, ?int $startAt, ?int $finishAt): array
    {
        $evidence = [];
        $readCap = $call['fresh'] ? 'high' : 'low';
        $finishId = $call['finishId'] ?? null;
        if ($finishId !== null && in_array($userId, array_filter([$call['finishAuthorId'] ?? null, $call['finishInitiatorId'] ?? null]), true)) {
            $evidence[] = ['rule' => 'finish-author', 'confidence' => 'high', 'messageId' => $finishId];
        }

        if (($call['initiatorId'] ?? null) === $userId) {
            $evidence[] = ['rule' => 'call-initiator', 'confidence' => 'medium'];
        }

        $read = $call['readers'][$userId] ?? null;
        $readAt = self::time($read['date'] ?? null);
        if ($read !== null && $finishId !== null && $finishAt !== null && $readAt !== null && $read['messageId'] >= $finishId) {
            $delta = $readAt - $finishAt;
            if ($delta >= 0 && $delta <= self::READ_AT_END_SECONDS) {
                $evidence[] = ['rule' => 'read-finish-at-end', 'confidence' => $readCap, 'messageId' => $read['messageId'], 'at' => $read['date'], 'deltaSeconds' => $delta];
            }
        }

        if ($startAt !== null && $finishAt !== null) {
            foreach ($call['messages'] ?? [] as $message) {
                $at = self::time($message['createdDate']);
                if ($message['authorId'] === $userId && $at !== null && $at >= $startAt && $at <= $finishAt) {
                    $evidence[] = ['rule' => 'message-during-call', 'confidence' => 'medium', 'messageId' => $message['id'], 'at' => $message['createdDate']];
                    break;
                }
            }
        }

        $startId = $call['startId'] ?? null;
        if ($read !== null && $startId !== null && $read['messageId'] < $startId) {
            $evidence[] = ['rule' => 'last-read-before-start', 'confidence' => $readCap === 'high' ? 'high' : 'low', 'messageId' => $read['messageId'], 'at' => $read['date']];
        }

        $activityDate = $call['lastActivity'][$userId] ?? null;
        $activity = self::time($activityDate);
        if ($activity !== null && $startAt !== null && $activity < $startAt) {
            $evidence[] = ['rule' => 'last-activity-before-start', 'confidence' => 'high', 'at' => $activityDate];
        } elseif ($activity !== null && $startAt !== null && $finishAt !== null && $activity < $finishAt - self::LEFT_EARLY_SECONDS) {
            $evidence[] = ['rule' => 'last-activity-before-finish', 'confidence' => 'low', 'at' => $activityDate];
        }

        return $evidence;
    }

    /**
     * @return array{verdict: string, confidence: ?string, conflict: bool}
     */
    private static function verdict(array $evidence): array
    {
        $groups = [];
        foreach ($evidence as $item) {
            $groups[self::GROUPS[$item['rule']]][] = $item['confidence'];
        }

        $has = static fn (string $group): bool => isset($groups[$group]);
        if ($has('absence') && ($has('end') || $has('during') || $has('left'))) {
            return ['verdict' => 'unknown', 'confidence' => null, 'conflict' => true];
        }

        if ($has('left') && $has('end')) {
            return ['verdict' => 'unknown', 'confidence' => null, 'conflict' => true];
        }

        if ($has('left')) {
            return ['verdict' => 'left-early', 'confidence' => 'low', 'conflict' => false];
        }

        if ($has('end') || $has('during')) {
            return ['verdict' => 'present', 'confidence' => self::max([...$groups['end'] ?? [], ...$groups['during'] ?? []]), 'conflict' => false];
        }

        if ($has('absence')) {
            return ['verdict' => 'absent', 'confidence' => self::max($groups['absence']), 'conflict' => false];
        }

        return ['verdict' => 'unknown', 'confidence' => null, 'conflict' => false];
    }

    private static function max(array $levels): string
    {
        usort($levels, static fn (string $a, string $b): int => self::CONFIDENCE[$b] <=> self::CONFIDENCE[$a]);
        return $levels[0];
    }

    public static function time(mixed $value): ?int
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value)->getTimestamp();
        } catch (\Exception) {
            return null;
        }
    }
}
