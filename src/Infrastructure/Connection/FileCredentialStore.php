<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Failure;
use Symfony\Component\Dotenv\Dotenv;

final readonly class FileCredentialStore implements CredentialStore
{
    public function __construct(private FileProtection $protection)
    {
    }

    public static function hostname(string $webhook): string
    {
        if (preg_match('/[\x00-\x20\x7f]/', $webhook) === 1) {
            throw new Failure('configuration-error', 'Provide a valid full HTTPS incoming webhook URL without whitespace or control characters.');
        }

        $url = parse_url($webhook);
        if (!is_array($url) || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || !ProfileRepository::domain($url['host'] ?? null) || preg_match('~^/rest/[1-9][0-9]*/[A-Za-z0-9_-]+/$~D', $url['path'] ?? '') !== 1) {
            throw new Failure('configuration-error', 'Provide a valid full HTTPS incoming webhook URL.');
        }

        return strtolower($url['host']);
    }

    public static function assertEnvironmentPath(string $path): void
    {
        if (preg_match('~^([a-zA-Z][a-zA-Z0-9+.-]*):/{2}~', $path, $matches) === 1 && strtolower($matches[1]) !== 'file') {
            throw new Failure('configuration-error', 'Select a filesystem dotenv file for --env-file or B24CLI_ENV_FILE.');
        }
    }

    public static function environmentFile(string $path): string
    {
        self::assertEnvironmentPath($path);
        try {
            if (!is_file($path)) {
                throw new \RuntimeException();
            }

            $content = @file_get_contents($path);
            if ($content === false || strlen($content) > 1048576 || str_contains($content, '$')) {
                throw new \RuntimeException();
            }

            // Never expand process variables or execute shell commands from dotenv.
            $values = (new Dotenv())->parse($content);
            $webhook = $values['BITRIX24_WEBHOOK'] ?? $values['BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK'] ?? '';
            self::hostname($webhook);
            return $webhook;
        } catch (\Throwable) {
            throw new Failure('configuration-error', 'Cannot read a valid webhook from the selected environment file.');
        }
    }

    public function read(string $directory, string $reference): string
    {
        $path = $this->path($directory, $reference);
        $this->protection->check($directory);
        $this->protection->check($directory . '/credentials');
        $this->protection->check($path);
        return self::environmentFile($path);
    }

    public function write(string $directory, string $webhook): string
    {
        self::hostname($webhook);
        $this->protection->directory($directory . '/credentials');
        $reference = 'credentials/' . bin2hex(random_bytes(16)) . '.env';
        $path = $this->path($directory, $reference);
        $stream = $this->protection->create($path);
        try {
            $data = "BITRIX24_WEBHOOK='" . $webhook . "'\n";
            if (fwrite($stream, $data) !== strlen($data) || !fflush($stream) || (function_exists('fsync') && !fsync($stream))) {
                throw new Failure('configuration-error', 'Cannot persist the credential file.');
            }
        } catch (\Throwable $error) {
            @unlink($path);
            throw $error;
        } finally {
            fclose($stream);
        }

        return $reference;
    }

    public function delete(string $directory, string $reference): void
    {
        $path = $this->path($directory, $reference);
        $this->protection->noLinks($path);
        if (file_exists($path) && !@unlink($path)) {
            throw new Failure('configuration-error', 'Profile removed; a protected orphan credential remains in the credentials directory.');
        }
    }

    private function path(string $directory, string $reference): string
    {
        if (preg_match('~^credentials/[a-f0-9]{8,64}\.env$~D', $reference) !== 1) {
            throw new Failure('configuration-error', 'Invalid credentialFile; use a managed credentials reference.');
        }

        return $directory . '/' . $reference;
    }
}
