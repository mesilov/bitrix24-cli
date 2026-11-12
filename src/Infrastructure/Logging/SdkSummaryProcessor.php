<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Above debug, keeps only a safe summary of SDK request records: API method, requestId, HTTP status and duration.
 * Request parameters, bodies, headers and transport info never reach such records.
 */
final class SdkSummaryProcessor implements ProcessorInterface
{
    public const CHANNEL = 'sdk';
    private const PAYLOAD_KEYS = ['parameters', 'params', 'requestoptions', 'responseinfo', 'response', 'request', 'body', 'headers', 'result', 'payload', 'data', 'url', 'trace'];
    private const SUMMARY_KEYS = ['apimethod', 'apiversion', 'requestid', 'method', 'class', 'message', 'httpstatus', 'durationms'];

    /** @var array<string, float> */
    private array $started = [];

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($record->channel !== self::CHANNEL) {
            return $record;
        }

        $context = $record->context;
        $requestId = is_scalar($context['requestId'] ?? null) ? (string) $context['requestId'] : null;
        if ($record->message === 'getResponse.start') {
            if ($requestId !== null) {
                $this->started[$requestId] = microtime(true);
            }

            return $record->with(context: $this->only($context, ['apiMethod', 'apiVersion', 'requestId']));
        }

        if ($record->message === 'getResponse.end') {
            $summary = $this->only($context, ['apiMethod', 'requestId']);
            $status = $context['responseInfo']['http_code'] ?? null;
            if (is_int($status) && $status > 0) {
                $summary['httpStatus'] = $status;
            }

            if ($requestId !== null && isset($this->started[$requestId])) {
                $summary['durationMs'] = (int) round((microtime(true) - $this->started[$requestId]) * 1000);
                unset($this->started[$requestId]);
            }

            return $record->with(context: $summary);
        }

        $filtered = [];
        foreach ($context as $key => $value) {
            $name = strtolower((string) $key);
            if (in_array($name, self::PAYLOAD_KEYS, true)) {
                continue;
            }

            $filtered[$key] = in_array($name, self::SUMMARY_KEYS, true) || is_scalar($value) || $value === null ? $value : '[omitted]';
        }

        return $record->with(context: $filtered);
    }

    /**
     * @param array<array-key, mixed> $context
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function only(array $context, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $context) && (is_scalar($context[$key]) || $context[$key] === null)) {
                $result[$key] = $context[$key];
            }
        }

        return $result;
    }
}
