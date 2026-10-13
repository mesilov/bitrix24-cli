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
    #[Group('task-tags')]
    #[Group('happy-path')]
    public function testTagReplacementPreservationMixedUpdateAndClear(): void
    {
        $id = $this->createTask();
        $result = $this->invoke(['task:update', $id, '--tag', '#плановая', '--tag', 'Этап 1']);
        self::assertSame(['1.0'], $result['meta']['apiVersions']);
        $expected = ['#плановая', 'Этап 1'];
        $names = array_column($this->card($id, ['tags.id', 'tags.name'])['tags'], 'name');
        sort($names);
        self::assertSame($expected, $names);
        $title = $this->fixtureTitle();
        $result = $this->invoke(['task:update', $id, '--title', $title]);
        self::assertSame(['3.0'], $result['meta']['apiVersions']);
        $names = array_column($this->card($id, ['tags.id', 'tags.name'])['tags'], 'name');
        sort($names);
        self::assertSame($expected, $names);
        $result = $this->invoke(['task:update', $id, '--title', $title . '-mixed', '--tag', '#ошибка']);
        self::assertSame(['3.0', '1.0'], $result['meta']['apiVersions']);
        $card = $this->card($id, ['title', 'description', 'tags.id', 'tags.name']);
        self::assertSame($title . '-mixed', $card['title']);
        self::assertSame('original', $card['description']);
        self::assertSame(['#ошибка'], array_column($card['tags'], 'name'));
        $this->invoke(['task:update', $id, '--clear-tags']);
        self::assertSame([], $this->card($id, ['tags.id', 'tags.name'])['tags']);
    }
}
