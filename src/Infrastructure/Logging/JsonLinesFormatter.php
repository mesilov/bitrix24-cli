<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/**
 * Formats one record as one JSON line with datetime, level, channel, message, context and runId.
 */
final readonly class JsonLinesFormatter implements FormatterInterface
{
    public const MAX_RECORD_BYTES = 65536;

    public function __construct(private string $runId)
    {
    }

    public function format(LogRecord $record): string
    {
        $data = [
            'datetime' => $record->datetime->format('Y-m-d\TH:i:s.uP'),
            'level' => strtolower($record->level->getName()),
            'channel' => $record->channel,
            'message' => $record->message,
            // Strings are already bounded by SizeLimitProcessor; here only objects become JSON-safe values.
            'context' => (object) SizeLimitProcessor::normalize($record->context, 0, false),
            'runId' => $this->runId,
        ];
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
        $line = (string) json_encode($data, $flags);
        if (strlen($line) + 1 > self::MAX_RECORD_BYTES) {
            $data['message'] = SizeLimitProcessor::truncate($record->message, 1024);
            $data['context'] = (object) ['truncated' => true, 'originalBytes' => strlen($line)];
            $line = (string) json_encode($data, $flags);
        }

        return $line . "\n";
    }

    public function formatBatch(array $records): string
    {
        return implode('', array_map($this->format(...), $records));
    }
}
