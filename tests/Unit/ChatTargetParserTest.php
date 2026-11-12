<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Chat\ChatTarget;
use Bitrix24\CLI\Application\Chat\ChatTargetParser;
use Bitrix24\CLI\Application\Failure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChatTargetParserTest extends TestCase
{
    public static function supported(): iterable
    {
        // [input, kind, id, source, host, messageId]
        yield 'typed chat' => ['chat123', 'chat', 123, 'id', null, null];
        yield 'typed project sg' => ['sg234', 'project', 234, 'id', null, null];
        yield 'typed project group' => ['group:234', 'project', 234, 'id', null, null];
        yield 'typed task' => ['task:5288', 'task', 5288, 'id', null, null];
        yield 'trimmed' => ["  chat7\n", 'chat', 7, 'id', null, null];
        yield 'messenger chat' => ['https://portal.example/online/?IM_DIALOG=chat9636', 'chat', 9636, 'url', 'portal.example', null];
        yield 'messenger project' => ['https://portal.example/online/?IM_DIALOG=sg234', 'project', 234, 'url', 'portal.example', null];
        yield 'message link selects chat' => ['https://Portal.Example./online/?IM_DIALOG=chat9636&IM_MESSAGE=19436', 'chat', 9636, 'url', 'portal.example', 19436];
        yield 'encoded dialog' => ['https://portal.example/online/?IM_DIALOG=%63hat5', 'chat', 5, 'url', 'portal.example', null];
        yield 'personal task path' => ['https://portal.example/company/personal/user/1/tasks/task/view/5288/?ta_sec=chat_tasks&ta_el=view_button', 'task', 5288, 'url', 'portal.example', null];
        yield 'group task path before project' => ['https://portal.example/workgroups/group/234/tasks/task/view/77/', 'task', 77, 'url', 'portal.example', null];
        yield 'project path' => ['https://portal.example/workgroups/group/234/?scrum=y', 'project', 234, 'url', 'portal.example', null];
        yield 'fragment ignored' => ['https://portal.example/workgroups/group/234/#chat99', 'project', 234, 'url', 'portal.example', null];
        yield 'explicit 443' => ['https://portal.example:443/workgroups/group/9', 'project', 9, 'url', 'portal.example', null];
    }

    #[DataProvider('supported')]
    public function testSupportedTargetsKeepIdTypesApart(string $input, string $kind, int $id, string $source, ?string $host, ?int $messageId): void
    {
        $target = (new ChatTargetParser())->parse($input);
        self::assertSame([$kind, $id, $source, $host, $messageId], [$target->kind, $target->id, $target->source, $target->host, $target->messageId]);
    }

    public function testNormalizedTargetNames(): void
    {
        self::assertSame('sg234', (new ChatTarget('project', 234, 'id'))->target());
        self::assertSame('chat5', (new ChatTarget('chat', 5, 'id'))->target());
        self::assertSame('task:7', (new ChatTarget('task', 7, 'id'))->target());
    }

    public static function rejected(): iterable
    {
        yield 'bare number' => ['234'];
        yield 'zero id' => ['chat0'];
        yield 'leading zero' => ['sg0234'];
        yield 'negative' => ['task:-5'];
        yield 'unknown prefix' => ['project:5'];
        yield 'uppercase prefix' => ['CHAT5'];
        yield 'empty' => ['   '];
        yield 'inner space' => ['chat 5'];
        yield 'invalid utf8' => ["\xff"];
        yield 'http' => ['http://portal.example/workgroups/group/234/'];
        yield 'userinfo' => ['https://user:pass@portal.example/workgroups/group/234/'];
        yield 'port' => ['https://portal.example:8443/workgroups/group/234/'];
        yield 'no host' => ['https:///workgroups/group/234/'];
        yield 'messenger without dialog' => ['https://portal.example/online/'];
        yield 'personal dialog' => ['https://portal.example/online/?IM_DIALOG=15'];
        yield 'unknown dialog' => ['https://portal.example/online/?IM_DIALOG=crm5'];
        yield 'bad message id' => ['https://portal.example/online/?IM_DIALOG=chat5&IM_MESSAGE=x'];
        yield 'repeated dialog' => ['https://portal.example/online/?IM_DIALOG=chat5&IM_DIALOG=chat6'];
        yield 'misspelled case' => ['https://portal.example/online/?im_dialog=chat5'];
        yield 'double encoded' => ['https://portal.example/online/?IM_DIALOG=%2563hat5'];
        yield 'dialog on project path' => ['https://portal.example/workgroups/group/234/?IM_DIALOG=chat5'];
        yield 'message without messenger' => ['https://portal.example/workgroups/group/234/?IM_MESSAGE=5'];
        yield 'task list path' => ['https://portal.example/workgroups/group/234/tasks/'];
        yield 'dot segments' => ['https://portal.example/workgroups/group/234/../235/'];
        yield 'unrelated path' => ['https://portal.example/crm/deal/details/5/'];
    }

    #[DataProvider('rejected')]
    public function testUnsupportedOrAmbiguousTargetsAreUsageErrors(string $input): void
    {
        try {
            (new ChatTargetParser())->parse($input);
            self::fail('Expected a usage error for ' . $input);
        } catch (Failure $failure) {
            self::assertSame('usage-error', $failure->errorCode);
            self::assertSame(2, $failure->exitStatus);
        }
    }
}
