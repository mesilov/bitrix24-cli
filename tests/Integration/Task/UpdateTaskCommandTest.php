<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;
use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:update')]
final class UpdateTaskCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathChangesTitleAndKeepsOmittedDescription(): void
    {
        $id = $this->createTask();
        $title = 'b24cli-ёж-' . bin2hex(random_bytes(8));
        $result = $this->invoke(['task:update', $id, '--title', $title]);
        self::assertTrue($result['data']['operation']['confirmed']);
        $card = $this->card($id);
        self::assertSame($title, $card['title']);
        self::assertSame('original', $card['description']);
    }

    public function testEmptyDescriptionClearsWithoutChangingTitle(): void
    {
        $title = $this->fixtureTitle();
        $id = $this->createTask($title);
        $this->invoke(['task:update', $id, '--description', '']);
        $card = $this->card($id);
        self::assertSame('', $card['description']);
        self::assertSame($title, $card['title']);
    }

    public function testRestrictedRoleCannotEditTask(): void
    {
        $webhook = $this->setting('B24CLI_TEST_RESTRICTED_WEBHOOK');
        if ($webhook === '') {
            self::markTestSkipped('Denied-role case needs B24CLI_TEST_RESTRICTED_WEBHOOK for a non-admin without edit access.');
        }

        $title = $this->fixtureTitle();
        $id = $this->createTask($title);
        $resolver = new readonly class ($webhook) implements ConnectionResolver {
            public function __construct(private string $webhook)
            {
            }
            public function webhook(): string
            {
                return $this->webhook;
            }
        };
        $app = (new ApplicationFactory())->create($this->root(), null, $resolver);
        $result = $this->invoke(['task:update', $id, '--title', 'unauthorized-change'], [1], $app);
        self::assertSame('permission-denied', $result['error']['code']);
        self::assertSame($title, $this->card($id)['title']);
    }
}
