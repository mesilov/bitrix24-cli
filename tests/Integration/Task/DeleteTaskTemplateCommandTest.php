<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskTemplateCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:template:delete')]
#[Group('task-templates')]
final class DeleteTaskTemplateCommandTest extends LiveTaskTemplateCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathUsesNativeTemplateContract(): void
    {
        $id = $this->createTemplate();
        $plan = $this->invoke(['task:template:delete', $id, '--dry-run']);
        self::assertTrue($plan['meta']['dryRun']);
        self::assertNotNull($this->templates->get($id));
        $json = $this->invoke(['task:template:delete', $id, '--force'], [3]);
        self::assertFalse($json['data']['operation']['confirmed']);
        self::assertFalse($json['data']['operation']['visibleAfter']);
        self::assertFalse($json['meta']['complete']);
        self::assertNull($this->templates->get($id));
    }
}
