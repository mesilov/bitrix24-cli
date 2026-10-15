<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskTemplateCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:template:update')]
#[Group('task-templates')]
final class UpdateTaskTemplateCommandTest extends LiveTaskTemplateCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathUsesNativeTemplateContract(): void
    {
        $id = $this->createTemplate();
        $before = $this->templates->get($id);
        $json = $this->invoke(['task:template:update', $id, '--description', 'Изменено через CLI']);
        self::assertTrue($json['data']['operation']['confirmed']);
        $after = $this->templates->get($id);
        self::assertSame('Изменено через CLI', $after['DESCRIPTION']);
        self::assertSame($before['TITLE'], $after['TITLE']);
    }
}
