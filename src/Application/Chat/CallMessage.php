<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

/**
 * Recognizes call service messages by params.COMPONENT_ID only; the localized text is never parsed.
 */
final class CallMessage
{
    public const COMPONENT_ID = 'CallMessage';

    /**
     * @return array{callId: int, event: 'start'|'finish', initiatorId: ?int, text: ?string}|null
     */
    public static function parse(mixed $params): ?array
    {
        if (!is_array($params) || ($params['COMPONENT_ID'] ?? null) !== self::COMPONENT_ID || !is_array($params['COMPONENT_PARAMS'] ?? null)) {
            return null;
        }

        $component = $params['COMPONENT_PARAMS'];
        $event = match ($component['MESSAGE_TYPE'] ?? null) {
            'START' => 'start',
            'FINISH' => 'finish',
            default => null,
        };
        $callId = self::positive($component['CALL_ID'] ?? null);
        if ($event === null || $callId === null) {
            return null;
        }

        $text = $component['MESSAGE_TEXT'] ?? null;
        return ['callId' => $callId, 'event' => $event, 'initiatorId' => self::positive($component['INITIATOR_ID'] ?? null), 'text' => is_string($text) ? $text : null];
    }

    /**
     * Adds kind and callRef to normalized chat messages and drops the internal params.
     */
    public static function mark(array $items): array
    {
        return array_map(static function (array $item): array {
            $call = self::parse($item['params'] ?? null);
            unset($item['params']);
            $item['kind'] = $call !== null ? 'call' : ((int) ($item['authorId'] ?? 0) === 0 ? 'system' : 'text');
            if ($call !== null) {
                $item['callRef'] = ['callId' => $call['callId'], 'event' => $call['event']];
            }

            return $item;
        }, $items);
    }

    public static function positive(mixed $value): ?int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value > 0 ? (int) $value : null;
        }

        return null;
    }
}
