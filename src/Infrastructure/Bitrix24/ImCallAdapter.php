<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Chat\CallMessage;
use Bitrix24\CLI\Application\Chat\Port\CallGateway;
use Bitrix24\CLI\Application\Failure;

/**
 * im.call.get is undocumented (checked against the official docs MCP); call.followup.get is REST 3.0, scope call.
 * The im.call.get response passes through a field whitelist right here, so connectionData,
 * publicChannels and logToken never reach the application, output, logs or errors.
 */
final readonly class ImCallAdapter implements CallGateway
{
    public const CALL_FIELDS = ['ID', 'UUID', 'TYPE', 'SCHEME', 'PROVIDER', 'STATE', 'INITIATOR_ID', 'IS_PUBLIC', 'PUBLIC_ID', 'CHAT_ID', 'START_DATE', 'END_DATE', 'LOG_URL', 'RECORD_AUDIO', 'AI_ANALYZE'];
    public const AI_FIELDS = ['serviceEnabled', 'settingsEnabled', 'agreementAccepted', 'tariffAvailable'];
    public const FOLLOWUP_SELECT = ['callId', 'chatId', 'startDate', 'endDate', 'durationSeconds', 'participants', 'outcomes'];

    public function __construct(private ApiTransport $api)
    {
    }

    public function details(int $callId): array
    {
        try {
            $result = self::whitelist($this->api->call('im.call.get', 1, ['callId' => $callId])->result);
        } catch (Failure $failure) {
            return ['status' => 'unavailable', 'reason' => $this->reason($failure, 'call')];
        }

        if (CallMessage::positive($result['call']['ID'] ?? null) !== $callId) {
            return ['status' => 'unavailable', 'reason' => 'call-details-unexpected'];
        }

        return ['status' => 'ok', ...$result];
    }

    public function followup(int $callId): array
    {
        try {
            $result = $this->api->call('call.followup.get', 3, ['callId' => $callId, 'select' => self::FOLLOWUP_SELECT, 'mentionFormat' => 'none'])->result;
        } catch (Failure $failure) {
            return ['status' => 'unavailable', 'reason' => $this->reason($failure, 'followup')];
        }

        $item = is_array($result['item'] ?? null) ? $result['item'] : null;
        if ($item === null || CallMessage::positive($item['callId'] ?? null) !== $callId || !is_array($item['participants'] ?? null)) {
            return ['status' => 'unavailable', 'reason' => 'followup-unexpected'];
        }

        $participants = [];
        foreach ($item['participants'] as $participant) {
            $userId = is_array($participant) ? CallMessage::positive($participant['userId'] ?? null) : null;
            if ($userId !== null) {
                $seconds = $participant['talkedSeconds'] ?? null;
                $participants[$userId] = ['userId' => $userId, 'name' => is_string($participant['name'] ?? null) ? $participant['name'] : null, 'talkedSeconds' => is_int($seconds) ? $seconds : null];
            }
        }

        ksort($participants);
        return ['status' => 'ok', 'participants' => array_values($participants)];
    }

    /**
     * @return array{call: array, users: list<int>, userData: array<int, array{name: ?string, role: ?string}>, ai: array}
     */
    public static function whitelist(array $result): array
    {
        $call = is_array($result['call'] ?? null) ? array_intersect_key($result['call'], array_flip(self::CALL_FIELDS)) : [];
        $users = [];
        foreach (is_array($result['users'] ?? null) ? $result['users'] : [] as $id) {
            if (($id = CallMessage::positive($id)) !== null) {
                $users[$id] = $id;
            }
        }

        $userData = [];
        foreach (is_array($result['userData'] ?? null) ? $result['userData'] : [] as $key => $row) {
            $id = CallMessage::positive(is_array($row) ? ($row['id'] ?? $key) : null);
            if ($id !== null) {
                $userData[$id] = ['name' => is_string($row['name'] ?? null) ? $row['name'] : null, 'role' => is_string($row['role'] ?? null) ? $row['role'] : null];
            }
        }

        $ai = [];
        foreach (self::AI_FIELDS as $field) {
            if (is_bool($result['ai'][$field] ?? null)) {
                $ai[$field] = $result['ai'][$field];
            }
        }

        ksort($users);
        return ['call' => $call, 'users' => array_values($users), 'userData' => $userData, 'ai' => $ai];
    }

    private function reason(Failure $failure, string $source): string
    {
        if ($failure->exitStatus === 130) {
            throw $failure;
        }

        $code = strtolower((string) ($failure->details['apiErrorCode'] ?? ''));
        return match (true) {
            str_contains($code, 'methodnotfound') || $code === 'error_method_not_found' => $source . '-method-unavailable',
            str_contains($code, 'insufficientscope') || $code === 'insufficient_scope' => $source . '-scope-missing',
            str_contains($code, 'entitynotfound') || $code === 'not_found' || $code === 'error_not_found' => $source . '-not-found',
            $failure->errorCode === 'permission-denied' => $source . '-access-denied',
            default => $source === 'call' ? 'call-details-error' : 'followup-error',
        };
    }
}
