<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\SDK\Core\Batch;
use Bitrix24\SDK\Core\BulkItemsReader\BulkItemsReaderBuilder;
use Bitrix24\SDK\Core\CoreBuilder;
use Bitrix24\SDK\Core\Credentials\Credentials;
use Bitrix24\SDK\Core\Credentials\WebhookUrl;
use Bitrix24\SDK\Services\ServiceBuilder;
use Bitrix24\SDK\Events\PortalDomainUrlChangingEvent;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class B24ClientProvider
{
    private ?ServiceBuilder $builder = null;
    private ?float $timeout = null;
    private string $webhook = '';
    private int $generation = -1;

    public function __construct(private readonly ConnectionResolver $resolver, private readonly RuntimeState $state, private readonly ?HttpClientInterface $httpClient = null, private readonly ?\Bitrix24\CLI\Infrastructure\Logging\LoggingRuntime $logging = null)
    {
    }

    public function get(): ServiceBuilder
    {
        if ($this->builder instanceof \Bitrix24\SDK\Services\ServiceBuilder && $this->timeout === $this->state->timeout && $this->generation === $this->state->generation) {
            return $this->builder;
        }

        $webhook = $this->resolver->webhook();
        $this->webhook = $webhook;
        // Every webhook seen by this process stays redacted in later log records, including after a generation change.
        $this->logging?->secret($webhook);
        $this->logging?->secret(basename(rtrim($webhook, '/')));
        try {
            $sdkLogger = $this->logging?->sdkLogger() ?? new NullLogger();
            $eventDispatcher = new EventDispatcher();
            $eventDispatcher->addListener(PortalDomainUrlChangingEvent::class, static function (PortalDomainUrlChangingEvent $event): void {
                $event->deny('Configure the new portal URL explicitly before retrying.');
            });
            $core = (new CoreBuilder())->withLogger($sdkLogger)
                ->withEventDispatcher($eventDispatcher)
                ->withCredentials(Credentials::createFromWebhook(new WebhookUrl($webhook)))
                ->withHttpClient(new \Bitrix24\CLI\Infrastructure\Bitrix24\StreamingJsonHttpClient($this->httpClient ?? HttpClient::create(['timeout' => $this->state->timeout, 'max_duration' => $this->state->timeout])))
                ->build();
            $batch = new Batch($core, $sdkLogger);
            $this->builder = new ServiceBuilder($core, $batch, (new BulkItemsReaderBuilder($core, $batch, $sdkLogger))->build(), $sdkLogger);
            $this->timeout = $this->state->timeout;
            $this->generation = $this->state->generation;
            return $this->builder;
        } catch (\Throwable) {
            throw new Failure('configuration-error', 'The webhook configuration is invalid; check the HTTPS incoming webhook URL.');
        }
    }

    public function redact(string $message): string
    {
        $token = basename(rtrim($this->webhook, '/'));
        return str_replace(array_filter([$this->webhook, $token]), '[redacted]', $message);
    }

    public function resolvedWebhook(): string
    {
        $this->get();
        return $this->webhook;
    }
}
