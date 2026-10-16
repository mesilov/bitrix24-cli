<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskTemplateCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:template:fields:list')]
#[Group('task-templates')]
final class ListTaskTemplateFieldsCommandTest extends LiveTaskTemplateCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathUsesNativeTemplateContract(): void
    {
        $json = $this->invoke(['task:template:fields:list']);
        self::assertArrayHasKey('TITLE', $json['data']['fields']);
        self::assertSame($this->templates->fields(), $json['data']['fields']);
    }
}
