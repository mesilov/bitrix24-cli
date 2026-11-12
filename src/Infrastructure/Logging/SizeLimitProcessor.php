<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Bounds context data: long strings are truncated, base64-like and binary values become their length.
 */
final class SizeLimitProcessor implements ProcessorInterface
{
    public const MAX_STRING_BYTES = 4096;
    private const BASE64_MIN_BYTES = 256;
    private const MAX_DEPTH = 8;
    private const MAX_ITEMS = 200;

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(message: self::limit($record->message), context: self::normalize($record->context));
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public static function normalize(array $values, int $depth = 0, bool $limitStrings = true): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['[depth limit]'];
        }

        $result = [];
        $count = 0;
        foreach ($values as $key => $value) {
            if (++$count > self::MAX_ITEMS) {
                $result['[truncated items]'] = count($values) - self::MAX_ITEMS;
                break;
            }

            $result[$key] = self::value($value, $depth, $limitStrings);
        }

        return $result;
    }

    public static function limit(string $value): string
    {
        $length = strlen($value);
        if (!mb_check_encoding($value, 'UTF-8')) {
            return '[binary ' . $length . ' bytes]';
        }

        if ($length >= self::BASE64_MIN_BYTES && preg_match('~^[A-Za-z0-9+/=\r\n]+$~D', $value) === 1) {
            return '[base64 ' . $length . ' bytes]';
        }

        return self::truncate($value, self::MAX_STRING_BYTES);
    }

    public static function truncate(string $value, int $bytes): string
    {
        $length = strlen($value);
        if ($length <= $bytes) {
            return $value;
        }

        return mb_strcut($value, 0, $bytes, 'UTF-8') . '…[truncated ' . ($length - $bytes) . ' bytes]';
    }

    private static function value(mixed $value, int $depth, bool $limitStrings = true): mixed
    {
        return match (true) {
            is_string($value) => $limitStrings ? self::limit($value) : $value,
            is_array($value) => self::normalize($value, $depth + 1, $limitStrings),
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            $value instanceof \Throwable => ['class' => $value::class, 'message' => self::limit($value->getMessage())],
            $value instanceof \JsonSerializable => self::value($value->jsonSerialize(), $depth + 1, $limitStrings),
            $value instanceof \stdClass => self::normalize(get_object_vars($value), $depth + 1, $limitStrings),
            is_object($value) => '[object ' . $value::class . ']',
            is_resource($value) => '[resource]',
            is_float($value) && !is_finite($value) => (string) $value,
            default => $value,
        };
    }
}
