<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

use Bitrix24\CLI\Application\Chat\Port\CallGateway;
use Bitrix24\CLI\Application\Chat\Port\ChatReadGateway;
use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\User\Port\UserPresenceGateway;

/**
 * Builds one call record per call ID seen in a chat history window.
 * Failures of call sources never change the completeness or exit status of the history.
 */
final readonly class CallEnricher
{
    private const FRESH_SECONDS = 3600;
    private const FOLLOWUP_DISABLING = ['followup-method-unavailable', 'followup-scope-missing'];

    public function __construct(private CallGateway $calls, private ChatReadGateway $reads, private UserPresenceGateway $users, private Clock $clock)
    {
    }

    /**
     * @param list<array> $items normalized messages that still carry params
     * @param array<int, array> $knownDetails call details already read by the caller, by call ID
     * @param list<int> $requiredCallIds calls that get a record even without messages in the window
     * @return array{calls: list<array>, meta: array{found: int, enriched: int, unavailable: int, confirmed: int}}
     */
    public function enrich(int $chatId, array $items, array $knownDetails = [], array $requiredCallIds = []): array
    {
        $found = $this->collect($items);
        foreach ($requiredCallIds as $callId) {
            $found[$callId] ??= [];
        }

        ksort($found);
        $meta = ['found' => count($found), 'enriched' => 0, 'unavailable' => 0, 'confirmed' => 0];
        if ($found === []) {
            return ['calls' => [], 'meta' => $meta];
        }

        $now = $this->clock->now();
        $caveats = [];
        try {
            $readState = $this->reads->readState($chatId);
        } catch (Failure $failure) {
            $this->rethrowInterrupt($failure);
            $readState = ['lastMessageId' => null, 'readers' => []];
            $caveats[] = 'read-state-unavailable';
        }

        $details = [];
        $followups = [];
        $followupDisabled = null;
        foreach (array_keys($found) as $callId) {
            $details[$callId] = $knownDetails[$callId] ?? $this->details($callId, $chatId);
            if ($followupDisabled !== null) {
                $followups[$callId] = ['status' => 'unavailable', 'reason' => $followupDisabled];
                continue;
            }

            $followups[$callId] = $this->followup($callId);
            if (in_array($followups[$callId]['reason'] ?? null, self::FOLLOWUP_DISABLING, true)) {
                $followupDisabled = $followups[$callId]['reason'];
            }
        }

        $eligibleIds = [];
        foreach (array_keys($found) as $callId) {
            $eligibleIds = [...$eligibleIds, ...$this->eligible($details[$callId], $readState)['ids']];
        }

        $eligibleIds = array_values(array_unique($eligibleIds));
        sort($eligibleIds);
        $activity = [];
        try {
            foreach ($eligibleIds === [] ? [] : $this->users->presence($eligibleIds) as $id => $user) {
                $activity[$id] = $user['lastActivityDate'];
            }
        } catch (Failure $failure) {
            $this->rethrowInterrupt($failure);
            $caveats[] = 'last-activity-unavailable';
        }

        $records = [];
        foreach ($found as $callId => $seen) {
            $record = $this->record($callId, $seen, $details[$callId], $followups[$callId], $readState, $activity, $items, $now, $caveats);
            $meta[$record['details']['status'] === 'ok' ? 'enriched' : 'unavailable']++;
            $meta['confirmed'] += $record['participants']['confirmed']['available'] ? 1 : 0;
            $records[] = $record;
        }

        return ['calls' => $records, 'meta' => $meta];
    }

    private function collect(array $items): array
    {
        $found = [];
        foreach ($items as $item) {
            $call = CallMessage::parse($item['params'] ?? null);
            if ($call === null) {
                continue;
            }

            $seen = $found[$call['callId']] ?? [];
            if ($call['event'] === 'start' && !isset($seen['start'])) {
                $seen['start'] = ['id' => $item['id'], 'createdDate' => $item['createdDate'] ?? null];
            }

            if ($call['event'] === 'finish' && !isset($seen['finish'])) {
                $seen['finish'] = ['id' => $item['id'], 'createdDate' => $item['createdDate'] ?? null, 'authorId' => CallMessage::positive($item['authorId'] ?? null), 'initiatorId' => $call['initiatorId'], 'text' => $call['text']];
            }

            $found[$call['callId']] = $seen;
        }

        ksort($found);
        return $found;
    }

    private function details(int $callId, int $chatId): array
    {
        try {
            $details = $this->calls->details($callId);
        } catch (Failure $failure) {
            $this->rethrowInterrupt($failure);
            return ['status' => 'unavailable', 'reason' => 'call-details-error'];
        }

        if ($details['status'] === 'ok' && CallMessage::positive($details['call']['CHAT_ID'] ?? null) !== $chatId) {
            return ['status' => 'unavailable', 'reason' => 'call-chat-mismatch'];
        }

        return $details;
    }

    private function followup(int $callId): array
    {
        try {
            return $this->calls->followup($callId);
        } catch (Failure $failure) {
            $this->rethrowInterrupt($failure);
            return ['status' => 'unavailable', 'reason' => 'followup-error'];
        }
    }

    private function eligible(array $details, array $readState): array
    {
        if ($details['status'] === 'ok' && $details['users'] !== []) {
            return ['source' => 'im.call.get.users', 'ids' => $details['users']];
        }

        return ['source' => 'im.dialog.get.readed_list', 'ids' => array_keys($readState['readers'])];
    }

    private function record(int $callId, array $seen, array $details, array $followup, array $readState, array $activity, array $items, \DateTimeImmutable $now, array $caveats): array
    {
        $ok = $details['status'] === 'ok';
        $call = $ok ? $details['call'] : [];
        $start = $seen['start'] ?? null;
        $finish = $seen['finish'] ?? null;
        $startedAt = $this->string($call['START_DATE'] ?? null) ?? $start['createdDate'] ?? null;
        $endedAt = $this->string($call['END_DATE'] ?? null) ?? $finish['createdDate'] ?? null;
        $startTime = ParticipantInference::time($startedAt);
        $endTime = ParticipantInference::time($endedAt);
        $finishTime = ParticipantInference::time($finish['createdDate'] ?? null);
        $fresh = $finish !== null && $finishTime !== null && $now->getTimestamp() - $finishTime < self::FRESH_SECONDS
            && ($readState['lastMessageId'] ?? null) === $finish['id'];
        $isPublic = in_array($call['IS_PUBLIC'] ?? null, [true, 'Y', 1, '1'], true);
        if ($isPublic) {
            $caveats[] = 'public-link-guests-invisible';
        }

        $eligible = $this->eligible($details, $readState);
        $confirmed = $followup['status'] === 'ok'
            ? ['available' => true, 'source' => 'call.followup.get', 'reason' => null, 'users' => $followup['participants']]
            : ['available' => false, 'source' => 'call.followup.get', 'reason' => $followup['reason'], 'users' => []];
        $confirmedIds = array_column($confirmed['users'], 'userId');
        $messages = [];
        foreach ($items as $item) {
            if (CallMessage::parse($item['params'] ?? null) === null && is_int($item['authorId'] ?? null) && $item['authorId'] > 0 && is_string($item['createdDate'] ?? null)) {
                $messages[] = ['id' => $item['id'], 'authorId' => $item['authorId'], 'createdDate' => $item['createdDate']];
            }
        }

        $inference = ParticipantInference::infer([
            'eligible' => array_values(array_diff($eligible['ids'], $confirmedIds)),
            'startId' => $start['id'] ?? null, 'startAt' => $startedAt, 'finishId' => $finish['id'] ?? null, 'finishAt' => $finish['createdDate'] ?? $endedAt,
            'finishAuthorId' => $finish['authorId'] ?? null, 'finishInitiatorId' => $finish['initiatorId'] ?? null,
            'initiatorId' => CallMessage::positive($call['INITIATOR_ID'] ?? null),
            'messages' => $messages, 'readers' => $readState['readers'], 'lastActivity' => $activity, 'fresh' => $fresh,
        ]);
        $duringIds = array_values(array_map(static fn (array $message): int => $message['id'], array_filter($messages, static function (array $message) use ($startTime, $endTime): bool {
            $at = ParticipantInference::time($message['createdDate']);
            return $startTime !== null && $endTime !== null && $at !== null && $at >= $startTime && $at <= $endTime;
        })));

        return [
            'callId' => $callId,
            'uuid' => $this->string($call['UUID'] ?? null),
            'details' => ['status' => $details['status'], 'source' => 'im.call.get', 'reason' => $details['reason'] ?? null],
            'state' => $this->string($call['STATE'] ?? null),
            'isPublic' => $ok ? $isPublic : null,
            'startedAt' => $startedAt,
            'endedAt' => $endedAt,
            'durationSeconds' => $startTime !== null && $endTime !== null && $endTime >= $startTime ? $endTime - $startTime : null,
            'durationText' => $finish['text'] ?? null,
            'initiatorId' => CallMessage::positive($call['INITIATOR_ID'] ?? null),
            'finishedById' => $finish['initiatorId'] ?? $finish['authorId'] ?? null,
            'messages' => ['startId' => $start['id'] ?? null, 'finishId' => $finish['id'] ?? null, 'duringIds' => $duringIds],
            'inWindow' => ['start' => $start !== null, 'finish' => $finish !== null],
            'recording' => $ok ? $this->recording($call, $details['ai']) : null,
            'participants' => [
                'eligible' => ['source' => $eligible['source'], 'note' => 'chat-members-not-attendees', 'users' => array_map(static fn (int $id): array => ['userId' => $id, 'name' => $details['userData'][$id]['name'] ?? null, 'role' => $details['userData'][$id]['role'] ?? null], $eligible['ids'])],
                'confirmed' => $confirmed,
                'inferred' => [
                    'rulesVersion' => ParticipantInference::RULES_VERSION,
                    'observedAt' => $now->format(\DATE_ATOM),
                    'evidenceFreshness' => $fresh ? 'fresh' : 'stale',
                    'caveats' => array_values(array_unique($caveats)),
                    ...$inference,
                ],
            ],
        ];
    }

    private function recording(array $call, array $ai): array
    {
        $audio = ($call['RECORD_AUDIO'] ?? false) === true;
        $aiAnalyze = ($call['AI_ANALYZE'] ?? false) === true;
        $reasons = [];
        if (!$audio && !$aiAnalyze) {
            foreach (['serviceEnabled' => 'ai-service-disabled', 'settingsEnabled' => 'ai-settings-disabled', 'agreementAccepted' => 'ai-agreement-not-accepted', 'tariffAvailable' => 'ai-tariff-unavailable'] as $flag => $reason) {
                if (($ai[$flag] ?? null) === false) {
                    $reasons[] = $reason;
                }
            }
        }

        return ['audio' => $audio, 'aiAnalyze' => $aiAnalyze, 'logUrl' => $this->string($call['LOG_URL'] ?? null), 'ai' => $ai, 'unavailableReasons' => $reasons];
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function rethrowInterrupt(Failure $failure): void
    {
        if ($failure->exitStatus === 130) {
            throw $failure;
        }
    }
}
