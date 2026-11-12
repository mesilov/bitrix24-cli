<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Task\Port\UnreadCounterGateway;
use Bitrix24\CLI\Application\Task\UnreadCounterSnapshot;

/**
 * REST 1.0 im.counters.get (scope im). CHAT_MUTED and the *_UNREAD id lists are documented; the task
 * sections TASKS_TASK (chat ID => counter) and TASKS_TASK_UNREAD (chat ID list) were observed on two
 * portals and are accepted only in exactly those forms. See openspec change add-task-unread-state.
 */
final readonly class Rest1ImCounterAdapter implements UnreadCounterGateway
{
    private const COUNTER_SECTIONS = ['TASKS_TASK', 'CHAT_MUTED'];

    private const MANUAL_SECTION = 'TASKS_TASK_UNREAD';

    public function __construct(private ApiTransport $api, private Clock $clock)
    {
    }

    public function snapshot(): UnreadCounterSnapshot
    {
        $result = $this->api->call('im.counters.get', 1, [])->result;
        $observedAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $reasons = [];
        $counters = [];
        $conflicts = [];
        $processed = 0;
        $countersComplete = true;
        $budgetExhausted = false;
        foreach (self::COUNTER_SECTIONS as $section) {
            $map = $result[$section] ?? null;
            if (!is_array($map) || ($map !== [] && array_is_list($map))) {
                // A missing section or a non-empty list is not a chat ID => counter map.
                $countersComplete = false;
                $reasons[] = 'unread-counter-schema-unsupported';
                continue;
            }

            foreach ($map as $key => $value) {
                if ($processed >= UnreadCounterSnapshot::ENTRY_LIMIT) {
                    $budgetExhausted = true;
                    break 2;
                }

                $processed++;
                $chatId = $this->id($key);
                if ($chatId === null || !is_int($value) || $value < 0) {
                    $countersComplete = false;
                    $reasons[] = 'unread-counter-schema-unsupported';
                    continue;
                }

                if (isset($counters[$chatId]) && $counters[$chatId] !== $value) {
                    $conflicts[$chatId] = true;
                    continue;
                }

                $counters[$chatId] = $value;
            }
        }

        $manual = null;
        $list = $result[self::MANUAL_SECTION] ?? null;
        if (!$budgetExhausted && is_array($list) && array_is_list($list)) {
            $manual = [];
            foreach ($list as $value) {
                if ($processed >= UnreadCounterSnapshot::ENTRY_LIMIT) {
                    $budgetExhausted = true;
                    $manual = null;
                    break;
                }

                $processed++;
                $chatId = $this->id($value);
                if ($chatId === null) {
                    $manual = null;
                    break;
                }

                $manual[$chatId] = true;
            }
        }

        if ($budgetExhausted) {
            $countersComplete = false;
            $reasons[] = 'unread-counter-budget-exhausted';
        }

        if ($manual === null) {
            $reasons[] = 'manual-unread-state-unavailable';
        }

        return new UnreadCounterSnapshot($counters, $conflicts, $manual, $countersComplete, $processed, $observedAt, array_values(array_unique($reasons)));
    }

    private function id(mixed $value): ?int
    {
        if (is_string($value) && preg_match('/^[1-9]\d{0,17}$/', $value) === 1) {
            $value = (int) $value;
        }

        return is_int($value) && $value >= 1 ? $value : null;
    }
}
