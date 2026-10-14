<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Task\StreamingBase64;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/** Preserve SDK request options while streaming an encoded local-file JSON value. */
final readonly class StreamingJsonHttpClient implements HttpClientInterface
{
    public function __construct(private HttpClientInterface $client)
    {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $content = $options['json']['fileContent'][1] ?? null;
        if (!$content instanceof StreamingBase64) {
            return $this->client->request($method, $url, $options);
        }

        $parameters = $options['json'];
        $name = $parameters['fileContent'][0];
        unset($parameters['fileContent'], $options['json']);
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $prefix = substr(json_encode($parameters, $flags), 0, -1) . ',"fileContent":[' . json_encode($name, $flags) . ',"';
        $suffix = '"]}';
        $options['headers']['Content-Type'] = 'application/json';
        $options['headers']['Content-Length'] = (string) (strlen($prefix) + $content->encodedLength + strlen($suffix));
        $options['body'] = (static function () use ($prefix, $content, $suffix): \Generator {
            yield $prefix;
            try {
                yield from $content->chunks();
            } catch (\Throwable $exception) {
                throw new \Symfony\Component\HttpClient\Exception\TransportException('Local upload stream stopped before confirmation.', 0, $exception);
            }

            yield $suffix;
        })();
        return $this->client->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return new self($this->client->withOptions($options));
    }
}
