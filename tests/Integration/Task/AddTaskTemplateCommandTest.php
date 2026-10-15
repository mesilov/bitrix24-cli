<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskTemplateCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:template:add')]
#[Group('task-templates')]
final class AddTaskTemplateCommandTest extends LiveTaskTemplateCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathUsesNativeTemplateContract(): void
    {
        $fields = $this->templateFields();
        $json = $this->invoke(['task:template:add', '--fields', json_encode($fields, JSON_THROW_ON_ERROR)]);
        $id = $json['data']['operation']['templateId'];
        self::assertTrue($json['data']['operation']['confirmed']);
        self::assertSame($fields['TITLE'], $this->templates->get($id)['TITLE']);
    }
}
