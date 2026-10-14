<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;
use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use Bitrix24\CLI\Tests\Support\CustomFieldPortalFixtures;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:update')]
final class UpdateTaskCommandTest extends LiveTaskCommandTestCase
{
    use CustomFieldPortalFixtures;

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

    #[Group('happy-path')]
    public function testCustomOnlyMixedClearingAndReadback(): void
    {
        $schema = $this->customSchema();
        $id = $this->createTask();
        $values = $this->customValues($schema, $this->fixtureTitle());
        $json = $this->invoke(['task:update', $id, '--user-fields', json_encode($values, JSON_THROW_ON_ERROR)]);
        self::assertSame(['1.0', '1.0'], array_column($json['meta']['apiCalls'], 'apiVersion'));
        $stored = $this->customCard($id);
        foreach ($values as $key => $value) {
            self::assertSame($value, $stored[$key]);
        }

        $this->receipt('custom-only-update', ['taskId' => $id, 'submitted' => $values, 'readback' => $stored, 'apiCalls' => $json['meta']['apiCalls']]);
        $mixedValue = $this->fixtureTitle();
        $mixed = $this->invoke(['task:update', $id, '--title', 'Mixed ' . $mixedValue, '--user-fields', json_encode([$schema['string'] => $mixedValue], JSON_THROW_ON_ERROR)]);
        self::assertSame(['1.0', '3.0', '1.0'], array_column($mixed['meta']['apiCalls'], 'apiVersion'));
        self::assertSame($mixedValue, $this->customCard($id)[$schema['string']]);
        $this->receipt('mixed-update', ['taskId' => $id, 'apiCalls' => $mixed['meta']['apiCalls'], 'readback' => $this->customCard($id)]);
        $clear = $this->invoke(['task:update', $id, '--user-fields', json_encode([$schema['string'] => '', $schema['string_multiple'] => []], JSON_THROW_ON_ERROR)]);
        $stored = $this->customCard($id);
        self::assertContains($stored[$schema['string']], [null, '']);
        self::assertContains($stored[$schema['string_multiple']], [null, []]);
        self::assertSame($values[$schema['string_req']], $stored[$schema['string_req']]);
        $this->receipt('custom-clearing', ['taskId' => $id, 'readback' => $stored, 'apiCalls' => $clear['meta']['apiCalls']]);
    }

    public function testUnknownStrictAndDryRunHaveNoMutations(): void
    {
        $schema = $this->customSchema();
        $id = $this->createTask();
        $json = $this->invoke(['task:update', $id, '--user-fields', '{"UF_B24CLI_MISSING":"x"}'], [2]);
        self::assertSame(['tasks.task.getFields'], array_column($json['meta']['apiCalls'], 'method'));
        $strict = $this->invoke(['task:update', $id, '--user-fields', json_encode([$schema['string'] => 'x'], JSON_THROW_ON_ERROR), '--api-policy', 'strict-rest3'], [4]);
        self::assertSame([], $strict['meta']['apiCalls']);
        $preview = $this->invoke(['task:update', $id, '--user-fields', json_encode([$schema['string'] => 'x'], JSON_THROW_ON_ERROR), '--dry-run']);
        self::assertSame(['tasks.task.getFields'], array_column($preview['meta']['apiCalls'], 'method'));
        self::assertNull($this->customCard($id)[$schema['string']]);
        $this->receipt('custom-preview-strict-unknown', ['taskId' => $id, 'unknownError' => $json['error'], 'strictError' => $strict['error'], 'previewPlan' => $preview['data']['plan']]);
    }
}
