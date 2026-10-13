<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;
use Bitrix24\CLI\Infrastructure\Connection\IncomingWebhookCurrentUserAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IncomingWebhookCurrentUserTest extends TestCase
{
    public static function invalidWebhooks(): iterable
    {
        foreach (['0', '-1', 'true', '1.0', '9999999999999999999999999999999999999999999'] as $id) {
            yield $id => ['https://portal.invalid/rest/' . $id . '/RecognizableSecret/'];
        }

        yield 'control character' => ["https://portal.invalid/rest/1/RecognizableSecret/\n"];
        yield 'query' => ['https://portal.invalid/rest/1/RecognizableSecret/?userId=2'];
    }

    private function adapter(string $webhook): IncomingWebhookCurrentUserAdapter
    {
        return new IncomingWebhookCurrentUserAdapter(new readonly class ($webhook) implements ConnectionResolver {
            public function __construct(private string $webhook)
            {
            }

            public function webhook(): string
            {
                return $this->webhook;
            }
        });
    }

    public function testValidatedWebhookContainsTheCurrentUserWithoutNetwork(): void
    {
        foreach ([1, 7, PHP_INT_MAX] as $id) {
            self::assertSame($id, $this->adapter('https://portal.invalid/rest/' . $id . '/RecognizableSecret/')->id());
        }
    }

    #[DataProvider('invalidWebhooks')]
    public function testInvalidIdentityNeverExposesCredentials(string $webhook): void
    {
        try {
            $this->adapter($webhook)->id();
            self::fail('Expected invalid webhook identity.');
        } catch (Failure $failure) {
            self::assertSame('configuration-error', $failure->errorCode);
            self::assertStringNotContainsString('RecognizableSecret', $failure->getMessage());
            self::assertStringNotContainsString('https://', $failure->getMessage());
        }
    }
}
