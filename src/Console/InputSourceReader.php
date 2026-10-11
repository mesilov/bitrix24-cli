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

    public function read(string $path): string
    {
        if ($path === '-') {
            $stream = $this->stream ?? (defined('STDIN') ? STDIN : null);
            if ($this->stdinUsed || !is_resource($stream) || stream_isatty($stream)) {
                throw Failure::usage('Use redirected stdin once, or provide an explicit file path.');
            }

            $this->stdinUsed = true;
            $data = stream_get_contents($stream);
        } else {
            $data = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
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
}
