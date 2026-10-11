<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:find')]
final class FindTaskCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReturnsTwoUnicodeTitleMatches(): void
    {
        $needle = 'b24cli-поиск-ёж-' . bin2hex(random_bytes(8));
        $firstTitle = 'Первый ' . $needle . ' договор';
        $secondTitle = 'Второй ' . $needle . ' договор';
        $first = $this->createTask($firstTitle);
        $second = $this->createTask($secondTitle);
        $result = $this->invoke(['task:find', '--title', mb_strtoupper($needle), '--all']);
        self::assertTrue($result['meta']['complete']);
        self::assertSame([['id' => $first, 'title' => $firstTitle], ['id' => $second, 'title' => $secondTitle]], $result['data']['items']);
        self::assertSame(2, $result['meta']['matched']);
        self::assertSame(2, $result['meta']['returned']);
        self::assertFalse($result['meta']['limitApplied']);
    }
}
