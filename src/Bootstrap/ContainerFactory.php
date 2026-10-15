<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Bootstrap;

use Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ContainerFactory
{
    public function create(string $root, ?ApiTransport $transport = null, ?ConnectionResolver $resolver = null, bool $packaged = false, ?\Symfony\Contracts\HttpClient\HttpClientInterface $httpClient = null, ?\Bitrix24\CLI\Application\Clock $clock = null, ?\Bitrix24\CLI\Application\Doctor\FeedbackGateway $feedbackGateway = null): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();
        $configure = require $root . '/config/services.php';
        $configure($containerBuilder, $root, $packaged);
        foreach ([ApiTransport::class => $transport, ConnectionResolver::class => $resolver, \Symfony\Contracts\HttpClient\HttpClientInterface::class => $httpClient, \Bitrix24\CLI\Application\Clock::class => $clock, \Bitrix24\CLI\Application\Doctor\FeedbackGateway::class => $feedbackGateway] as $id => $replacement) {
            if ($replacement !== null) {
                $containerBuilder->removeAlias($id);
                $containerBuilder->register($id)->setSynthetic(true)->setPublic(true);
            }
        }

        $containerBuilder->compile();
        foreach ([ApiTransport::class => $transport, ConnectionResolver::class => $resolver, \Symfony\Contracts\HttpClient\HttpClientInterface::class => $httpClient, \Bitrix24\CLI\Application\Clock::class => $clock, \Bitrix24\CLI\Application\Doctor\FeedbackGateway::class => $feedbackGateway] as $id => $replacement) {
            if ($replacement !== null) {
                $containerBuilder->set($id, $replacement);
            }
        }

        return $containerBuilder;
    }
}
