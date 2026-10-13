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
    public const MAX_BYTES = 10485760;

    public function __construct(private Filesystem $filesystem, private Base64Encoder $encoder)
    {
    }

    public function inspect(string $path, ?string $name = null): LocalFile
    {
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

        if ($stat['size'] > self::MAX_BYTES || $stat['size'] < 0) {
            throw Failure::usage('--file exceeds the CLI limit of 10 MiB (10485760 bytes).');
        }

        return new LocalFile($normalized, $name, $stat['size'], $this->identity($stat));
    }

    public function encode(LocalFile $file): string
    {
        $current = $this->inspect($file->path, $file->name);
        if ($current->identity !== $file->identity) {
            throw Failure::usage('--file changed after validation; inspect it before retrying.');
        }

        $handle = @fopen($file->path, 'rb');
        if ($handle === false) {
            throw Failure::usage('Cannot read --file; check local permissions.');
        }

        try {
            $before = fstat($handle);
            if ($before === false || ($before['mode'] & 0170000) !== 0100000 || $this->identity($before) !== $file->identity) {
                throw Failure::usage('--file changed after validation; upload was not sent.');
            }

            $bytes = @stream_get_contents($handle, self::MAX_BYTES + 1);
            $after = fstat($handle);
            if ($bytes === false || strlen($bytes) > self::MAX_BYTES || strlen($bytes) !== $file->sizeBytes || $after === false || $this->identity($after) !== $file->identity) {
                throw Failure::usage('--file could not be read unchanged within the CLI limit of 10 MiB; upload was not sent.');
            }

            return $this->encoder->encodeString($bytes);
        } finally {
            fclose($handle);
        }
    }

    private function identity(array $stat): array
    {
        return array_intersect_key($stat, array_flip(['dev', 'ino', 'size', 'mtime', 'ctime']));
    }
}
