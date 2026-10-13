<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Filesystem;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\LocalFile;
use Bitrix24\CLI\Application\Task\Port\LocalFileGateway;
use Bitrix24\SDK\Infrastructure\Filesystem\Base64Encoder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final readonly class LocalFileSource implements LocalFileGateway
{
    public const MAX_BYTES = LocalFile::DEFAULT_MAX_BYTES;
    public const DIRECT_ENCODING_BYTES = 10485760;

    public function __construct(private Filesystem $filesystem, private Base64Encoder $encoder, private \Bitrix24\CLI\Application\RuntimeState $state = new \Bitrix24\CLI\Application\RuntimeState())
    {
    }

    public function inspect(string $path, ?string $name = null, int $maxUploadBytes = LocalFile::DEFAULT_MAX_BYTES): LocalFile
    {
        if ($maxUploadBytes < 1) {
            throw Failure::usage('The upload limit must be a positive integer in bytes.');
        }

        if ($path === '' || $path === '-' || str_contains($path, "\0") || !Path::isLocal($path)
            || (preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) === 1 && preg_match('~^[a-z]:[\\\\/]~i', $path) !== 1)) {
            throw Failure::usage('--file requires a local regular file path, without URLs, stream wrappers or stdin (-).');
        }

        $normalized = Path::normalize($path);
        $name ??= basename($normalized);
        if ($name === '' || in_array($name, ['.', '..'], true) || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > 255 || preg_match('~[\\\\/\p{Cc}]~u', $name) === 1) {
            throw Failure::usage('--name or the file basename must be valid UTF-8, 1–255 characters, without path separators or control characters.');
        }

        try {
            $exists = $this->filesystem->exists($normalized);
        } catch (\Symfony\Component\Filesystem\Exception\IOExceptionInterface) {
            throw Failure::usage('Cannot inspect --file; provide a readable local regular file.');
        }

        clearstatcache(true, $normalized);
        $stat = $exists ? @stat($normalized) : false;
        if ($stat === false || ($stat['mode'] & 0170000) !== 0100000 || !is_readable($normalized)) {
            throw Failure::usage('--file must exist and be a readable regular file; directories and special files are not supported.');
        }

        if ($stat['size'] > $maxUploadBytes || $stat['size'] < 0) {
            throw Failure::usage('--file exceeds the configured upload limit of ' . $maxUploadBytes . ' bytes.');
        }

        return new LocalFile($normalized, $name, $stat['size'], $this->identity($stat), $maxUploadBytes);
    }

    public function encode(LocalFile $file): string|\Bitrix24\CLI\Application\Task\StreamingBase64
    {
        $this->verify($file);
        if ($file->sizeBytes <= self::DIRECT_ENCODING_BYTES) {
            $bytes = '';
            foreach ($this->bytes($file) as $chunk) {
                $bytes .= $chunk;
            }

            return $this->encoder->encodeString($bytes);
        }

        $length = intdiv($file->sizeBytes, 3) * 4 + ($file->sizeBytes % 3 === 0 ? 0 : 4);
        return new \Bitrix24\CLI\Application\Task\StreamingBase64($length, function () use ($file): \Generator {
            $remainder = '';
            foreach ($this->bytes($file) as $chunk) {
                $chunk = $remainder . $chunk;
                $aligned = intdiv(strlen($chunk), 3) * 3;
                if ($aligned > 0) {
                    yield str_replace("\r\n", '', $this->encoder->encodeString(substr($chunk, 0, $aligned)));
                }

                $remainder = substr($chunk, $aligned);
            }

            if ($remainder !== '') {
                yield str_replace("\r\n", '', $this->encoder->encodeString($remainder));
            }
        });
    }

    private function verify(LocalFile $file): void
    {
        $this->state->checkCancellation();
        $current = $this->inspect($file->path, $file->name, $file->maxUploadBytes);
        if ($current->identity !== $file->identity) {
            throw Failure::usage('--file changed after validation; inspect it before retrying.');
        }
    }

    private function bytes(LocalFile $file): \Generator
    {
        $this->verify($file);
        $handle = @fopen($file->path, 'rb');
        if ($handle === false) {
            throw Failure::usage('Cannot read --file; check local permissions.');
        }

        try {
            $before = fstat($handle);
            if ($before === false || ($before['mode'] & 0170000) !== 0100000 || $this->identity($before) !== $file->identity) {
                throw Failure::usage('--file changed after validation; upload was not sent.');
            }

            $total = 0;
            while (!feof($handle)) {
                $this->state->checkCancellation();
                $chunk = @fread($handle, 60000);
                if ($chunk === false || ($chunk === '' && !feof($handle))) {
                    throw Failure::usage('Cannot read the validated --file; check local permissions.');
                }

                $total += strlen($chunk);
                if ($total > $file->sizeBytes || $total > $file->maxUploadBytes) {
                    throw Failure::usage('--file changed or exceeded the configured upload limit during reading.');
                }

                if ($chunk !== '') {
                    yield $chunk;
                }
            }

            $after = fstat($handle);
            if ($total !== $file->sizeBytes || $after === false || $this->identity($after) !== $file->identity) {
                throw Failure::usage('--file could not be read unchanged; upload was not confirmed.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function identity(array $stat): array
    {
        return array_intersect_key($stat, array_flip(['dev', 'ino', 'size', 'mtime', 'ctime']));
    }
}
