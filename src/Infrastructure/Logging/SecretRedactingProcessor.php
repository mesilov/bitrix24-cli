<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Replaces known secrets, webhook tokens in REST paths and values of secret-named keys with [redacted].
 */
final readonly class SecretRedactingProcessor implements ProcessorInterface
{
    public const MASK = '[redacted]';
    private const SECRET_WORDS = ['auth', 'password', 'secret', 'token', 'webhook'];

    /**
     * @param \Closure(): list<string> $secrets Current and previous webhooks and tokens of this process.
     */
    public function __construct(private \Closure $secrets)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $secrets = array_values(array_filter(($this->secrets)(), static fn (string $secret): bool => strlen($secret) >= 4));
        usort($secrets, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        return $record->with(message: self::text($record->message, $secrets), context: self::context($record->context, $secrets, 0));
    }

    public static function secretKey(string|int $key): bool
    {
        if (is_int($key)) {
            return false;
        }

        $words = preg_split('/[^a-z0-9]+/', strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $key))) ?: [];
        return array_intersect($words, self::SECRET_WORDS) !== [];
    }

    /**
     * @param list<string> $secrets
     */
    public static function text(string $value, array $secrets): string
    {
        if ($secrets !== []) {
            $value = str_replace($secrets, self::MASK, $value);
        }

        // Incoming webhook paths carry the token after the user ID: /rest/{userId}/{token}/.
        return (string) preg_replace('~(/rest/\d+/)(?!\[redacted\])[^/\s"\'?#]+~', '$1' . self::MASK, $value);
    }

    /**
     * @param array<array-key, mixed> $context
     * @param list<string> $secrets
     * @return array<array-key, mixed>
     */
    private static function context(array $context, array $secrets, int $depth): array
    {
        $result = [];
        foreach ($context as $key => $value) {
            $result[$key] = self::secretKey($key) ? self::MASK : self::value($value, $secrets, $depth);
        }

        return $result;
    }

    /**
     * @param list<string> $secrets
     */
    private static function value(mixed $value, array $secrets, int $depth): mixed
    {
        if ($depth > 16) {
            return '[depth limit]';
        }

        return match (true) {
            is_string($value) => self::text($value, $secrets),
            is_array($value) => self::context($value, $secrets, $depth + 1),
            $value instanceof \stdClass => self::context(get_object_vars($value), $secrets, $depth + 1),
            $value instanceof \Throwable => ['class' => $value::class, 'message' => self::text($value->getMessage(), $secrets)],
            $value instanceof \JsonSerializable => self::value($value->jsonSerialize(), $secrets, $depth + 1),
            $value instanceof \Stringable => self::text((string) $value, $secrets),
            default => $value,
        };
    }
}
