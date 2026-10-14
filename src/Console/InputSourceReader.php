<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;

final class InputSourceReader
{
    private bool $stdinUsed = false;
    private mixed $stream = null;

    public function reset(?InputInterface $input = null): void
    {
        $this->stdinUsed = false;
        $this->stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
    }

    public function read(string $path, ?int $maximum = null): string
    {
        if ($path === '-') {
            $stream = $this->stream ?? (defined('STDIN') ? STDIN : null);
            if ($this->stdinUsed || !is_resource($stream) || stream_isatty($stream)) {
                throw Failure::usage('Use redirected stdin once, or provide an explicit file path.');
            }

            $this->stdinUsed = true;
            $data = $maximum === null ? stream_get_contents($stream) : stream_get_contents($stream, $maximum + 1);
        } else {
            $data = is_file($path) && is_readable($path) ? file_get_contents($path, false, null, 0, $maximum === null ? null : $maximum + 1) : false;
        }

        if ($maximum !== null && is_string($data) && strlen($data) > $maximum) {
            throw Failure::usage('Input exceeds the allowed size.');
        }

        if ($data === false) {
            throw Failure::usage('Cannot read the selected input file.');
        }

        return $data;
    }

    public function object(string $value): array
    {
        try {
            $decoded = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw Failure::usage('Provide a valid JSON object.');
        }

        if (!$decoded instanceof \stdClass) {
            throw Failure::usage('Provide a JSON object, not an array or scalar.');
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function uniqueObject(string $value): array
    {
        $fields = $this->object($value);
        $decoded = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
        foreach ($decoded as $item) {
            if (is_object($item) || (is_array($item) && array_any($item, static fn ($element): bool => is_object($element)))) {
                throw Failure::usage('Custom values cannot contain JSON objects.');
            }
        }

        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"\s*:/s', $value, $matches);
        $seen = [];
        foreach ($matches[0] as $token) {
            $key = json_decode(rtrim(substr($token, 0, -1)), true, 512, JSON_THROW_ON_ERROR);
            if (isset($seen[$key])) {
                throw Failure::usage('Duplicate JSON custom field key.');
            }

            $seen[$key] = true;
        }

        return $fields;
    }
}
