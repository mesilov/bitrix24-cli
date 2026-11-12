<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Logging;

use Bitrix24\CLI\Application\Logging\LoggingSettings;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Process-wide logger shared by CLI code and b24phpsdk. Until activate() enables file logging it has no handlers,
 * so records are dropped without filesystem access.
 */
final class LoggingRuntime
{
    public const CHANNEL = 'cli';

    private readonly Logger $logger;
    private readonly Logger $sdkLogger;
    private readonly string $runId;
    private bool $activated = false;
    private bool $warned = false;
    /** @var \Closure(string): void */
    private \Closure $warningSink;
    /** @var array<string, true> */
    private array $secrets = [];

    public function __construct(private readonly LoggingSettingsReader $reader, private readonly FileProtection $protection)
    {
        $this->logger = new Logger(self::CHANNEL);
        $this->sdkLogger = new Logger(SdkSummaryProcessor::CHANNEL);
        $this->runId = $this->uuid();
        $this->warningSink = static function (string $message): void {
        };
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    public function sdkLogger(): LoggerInterface
    {
        return $this->sdkLogger;
    }

    public function runId(): string
    {
        return $this->runId;
    }

    /**
     * Registers a webhook or token to redact in all later records of this process.
     */
    public function secret(string $value): void
    {
        if ($value !== '') {
            $this->secrets[$value] = true;
        }
    }

    /**
     * @param \Closure(string): void $warningSink
     */
    public function activate(?string $configDirectory, \Closure $warningSink): void
    {
        $this->warningSink = $warningSink;
        if ($this->activated) {
            return;
        }

        $this->activated = true;
        $state = $this->reader->read($configDirectory);
        if ($state['status'] === 'invalid') {
            $this->warn('Invalid logging settings in config.json; file logging is disabled for this run. ' . $state['reason']);
            return;
        }

        if ($state['status'] !== 'ok' || $state['directory'] === null) {
            return;
        }

        $settings = $state['settings'];
        $level = Level::fromName($settings['level']);
        $dailyMaskFileHandler = new DailyMaskFileHandler($state['directory'], $settings['fileMask'], $settings['retentionDays'], LoggingSettings::timezone(), $this->protection, $this->warn(...), $level);
        $dailyMaskFileHandler->setFormatter(new JsonLinesFormatter($this->runId));

        $sizeLimitProcessor = new SizeLimitProcessor();
        $secretRedactingProcessor = new SecretRedactingProcessor(fn (): array => array_keys($this->secrets));
        foreach ([$this->logger, $this->sdkLogger] as $logger) {
            $logger->pushHandler($dailyMaskFileHandler);
            // Monolog runs the last pushed processor first: summary, then redaction, then size limits.
            $logger->pushProcessor($sizeLimitProcessor);
            $logger->pushProcessor($secretRedactingProcessor);
        }

        if ($level !== Level::Debug) {
            $this->sdkLogger->pushProcessor(new SdkSummaryProcessor());
        }
    }

    public function enabled(): bool
    {
        return $this->logger->getHandlers() !== [];
    }

    public function warn(string $message): void
    {
        if ($this->warned) {
            return;
        }

        $this->warned = true;
        try {
            ($this->warningSink)('Warning: ' . SecretRedactingProcessor::text($message, array_keys($this->secrets)));
        } catch (\Throwable) {
            // A warning must never change the command result.
        }
    }

    public function close(): void
    {
        try {
            $this->logger->close();
            $this->sdkLogger->close();
        } catch (\Throwable) {
        }
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
