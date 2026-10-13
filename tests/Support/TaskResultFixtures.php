<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Infrastructure\Bitrix24\Rest3TaskResultAdapter;

trait TaskResultFixtures
{
    protected function createResult(int $taskId, string $text): int
    {
        return (new Rest3TaskResultAdapter($this->api))->add($taskId, $text);
    }

    protected function taskResults(int $taskId): array
    {
        $page = (new Rest3TaskResultAdapter($this->api))->page($taskId, 0, 50);
        self::assertNull($page->next, 'Disposable task results must fit the readback page.');
        return array_column($page->items, null, 'id');
    }
}
