<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskTemplateCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:template:show')]
#[Group('task-templates')]
final class ShowTaskTemplateCommandTest extends LiveTaskTemplateCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathUsesNativeTemplateContract(): void
    {
        $id = $this->createTemplate();
        $expected = $this->templates->get($id);
        $json = $this->invoke(['task:template:show', $id]);
        self::assertSame($expected, $json['data']['template']);
    }
}
