<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

/**
 * One im.counters.get observation for the connection user (#148).
 *
 * Counters merge the TASKS_TASK and CHAT_MUTED sections by chat ID; a chat whose sections disagree is
 * a conflict and stays unknown. A chat absent from complete sections has zero unread messages; once a
 * section is missing, malformed or cut by the entry limit, absence proves nothing. TASKS_TASK_UNREAD is
 * the separate manual "unread" marker set: it never adds to a counter.
 */
final readonly class UnreadCounterSnapshot
{
    public const ENTRY_LIMIT = 10000;

    public const SOURCE = 'im.counters.get';

    /**
     * @param array<int, int>       $counters    chat ID => non-negative unread counter
     * @param array<int, true>      $conflicts   chat IDs with disagreeing counters
     * @param array<int, true>|null $manualMarks chat IDs marked unread by hand; null when the section is unavailable
     * @param list<string>          $reasons     snapshot-wide incompleteness reasons
     */
    public function __construct(
        public array $counters,
        public array $conflicts,
        public ?array $manualMarks,
        public bool $countersComplete,
        public int $processedEntries,
        public string $observedAt,
        public array $reasons,
    ) {
    }

    /**
     * Unread counter of the chat, or null when the snapshot cannot prove it.
     */
    public function counter(int $chatId): ?int
    {
        if (isset($this->conflicts[$chatId])) {
            return null;
        }

        return $this->counters[$chatId] ?? ($this->countersComplete ? 0 : null);
    }

    public function conflict(int $chatId): bool
    {
        return isset($this->conflicts[$chatId]);
    }

    public function manuallyMarkedUnread(int $chatId): ?bool
    {
        return $this->manualMarks === null ? null : isset($this->manualMarks[$chatId]);
    }

    /**
     * @param list<string> $reasons all unread reasons of the run, snapshot-wide and per task
     */
    public function metadata(array $reasons): array
    {
        return [
            'source' => self::SOURCE,
            'observedAt' => $this->observedAt,
            'countersComplete' => $this->countersComplete,
            'manualMarksComplete' => $this->manualMarks !== null,
            'processedEntries' => $this->processedEntries,
            'entryLimit' => self::ENTRY_LIMIT,
            'reasons' => $reasons,
        ];
    }
}
