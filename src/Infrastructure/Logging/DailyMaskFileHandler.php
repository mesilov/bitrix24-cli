<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Logging;

use Bitrix24\CLI\Application\Logging\LoggingSettings;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Appends records to one file per local day named by the mask and removes mask-matching files older than the retention.
 * Any filesystem failure disables the handler for the rest of the process after a single warning.
 */
final class DailyMaskFileHandler extends AbstractProcessingHandler
{
    /** @var resource|null */
    private $stream;
    private ?string $currentPath = null;
    private bool $failed = false;

    /**
     * @param \Closure(string): void $warn
     */
    public function __construct(
        private readonly string $directory,
        private readonly string $mask,
        private readonly int $retentionDays,
        private readonly \DateTimeZone $timezone,
        private readonly FileProtection $protection,
        private readonly \Closure $warn,
        Level $level = Level::Debug,
    ) {
        parent::__construct($level);
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
        $this->currentPath = null;
        parent::close();
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new JsonLinesFormatter('');
    }

    protected function write(LogRecord $record): void
    {
        if ($this->failed) {
            return;
        }

        try {
            $date = $record->datetime->setTimezone($this->timezone);
            $path = $this->directory . DIRECTORY_SEPARATOR . LoggingSettings::fileName($this->mask, $date);
            if ($path !== $this->currentPath) {
                $this->open($path);
                $this->cleanup($date);
            }

            $line = (string) $record->formatted;
            if (!flock($this->stream, LOCK_EX)) {
                throw new \RuntimeException('lock');
            }

            try {
                if (fwrite($this->stream, $line) !== strlen($line)) {
                    throw new \RuntimeException('write');
                }

                fflush($this->stream);
            } finally {
                flock($this->stream, LOCK_UN);
            }
        } catch (\Throwable) {
            $this->fail();
        }
    }

    private function open(string $path): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
            $this->stream = null;
        }

        if (!is_dir($this->directory)) {
            // Only directories created by the CLI get the managed protection; existing user directories keep their rights.
            $this->protection->directory($this->directory);
        }

        $stream = null;
        if (!file_exists($path)) {
            try {
                $stream = $this->protection->create($path);
            } catch (\Throwable) {
                clearstatcache(true, $path);
                if (!file_exists($path)) {
                    throw new \RuntimeException('create');
                }
            }
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        $stream = @fopen($path, 'ab');
        if ($stream === false) {
            throw new \RuntimeException('open');
        }

        $this->stream = $stream;
        $this->currentPath = $path;
    }

    private function cleanup(\DateTimeImmutable $today): void
    {
        $pattern = LoggingSettings::pattern($this->mask);
        $oldest = $today->setTime(0, 0)->modify('-' . ($this->retentionDays - 1) . ' days')->format('Ymd');
        $entries = @scandir($this->directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if (preg_match($pattern, $entry, $match) !== 1 || !checkdate((int) $match['month'], (int) $match['day'], (int) $match['year'])) {
                continue;
            }

            if ($match['year'] . $match['month'] . $match['day'] < $oldest) {
                $file = $this->directory . DIRECTORY_SEPARATOR . $entry;
                if (is_file($file) && !is_link($file)) {
                    // Another process may delete the same file first; that is not an error.
                    @unlink($file);
                }
            }
        }
    }

    private function fail(): void
    {
        $this->failed = true;
        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }

        $this->stream = null;
        $this->currentPath = null;
        ($this->warn)('File logging is unavailable for this run; check logging.directory and its permissions.');
    }
}
