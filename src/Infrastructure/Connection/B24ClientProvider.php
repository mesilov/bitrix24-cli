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

    public function __construct(private readonly ConnectionResolver $resolver, private readonly RuntimeState $state, private readonly ?HttpClientInterface $httpClient = null)
    {
    }

    public function get(): ServiceBuilder
    {
        if ($this->builder instanceof \Bitrix24\SDK\Services\ServiceBuilder && $this->timeout === $this->state->timeout) {
            return $this->builder;
        }

        $webhook = $this->resolver->webhook();
        $this->webhook = $webhook;
        try {
            $nullLogger = new NullLogger();
            $eventDispatcher = new EventDispatcher();
            $eventDispatcher->addListener(PortalDomainUrlChangingEvent::class, static function (PortalDomainUrlChangingEvent $event): void {
                $event->deny('Configure the new portal URL explicitly before retrying.');
            });
            $core = (new CoreBuilder())->withLogger($nullLogger)
                ->withEventDispatcher($eventDispatcher)
                ->withCredentials(Credentials::createFromWebhook(new WebhookUrl($webhook)))
                ->withHttpClient($this->httpClient ?? HttpClient::create(['timeout' => $this->state->timeout, 'max_duration' => $this->state->timeout]))
                ->build();
            $batch = new Batch($core, $nullLogger);
            $this->builder = new ServiceBuilder($core, $batch, (new BulkItemsReaderBuilder($core, $batch, $nullLogger))->build(), $nullLogger);
            $this->timeout = $this->state->timeout;
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
}
