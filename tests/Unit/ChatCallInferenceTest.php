<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Chat\CallMessage;
use Bitrix24\CLI\Application\Chat\ParticipantInference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChatCallInferenceTest extends TestCase
{
    private const START = '2026-11-12T10:00:00+03:00';
    private const FINISH = '2026-11-12T10:30:00+03:00';

    public function testCallMessagesAreRecognizedOnlyByComponent(): void
    {
        self::assertSame(['callId' => 7, 'event' => 'start', 'initiatorId' => null, 'text' => null], CallMessage::parse(['COMPONENT_ID' => 'CallMessage', 'COMPONENT_PARAMS' => ['MESSAGE_TYPE' => 'START', 'CALL_ID' => 7]]));
        self::assertSame(['callId' => 7, 'event' => 'finish', 'initiatorId' => 22, 'text' => 'Звонок завершён (1 мин)'], CallMessage::parse(['COMPONENT_ID' => 'CallMessage', 'COMPONENT_PARAMS' => ['MESSAGE_TYPE' => 'FINISH', 'CALL_ID' => '7', 'INITIATOR_ID' => 22, 'MESSAGE_TEXT' => 'Звонок завершён (1 мин)']]));
        foreach ([null, [], ['NOTIFY' => 'N'], ['COMPONENT_ID' => 'Other', 'COMPONENT_PARAMS' => ['MESSAGE_TYPE' => 'START', 'CALL_ID' => 7]], ['COMPONENT_ID' => 'CallMessage', 'COMPONENT_PARAMS' => ['MESSAGE_TYPE' => 'RING', 'CALL_ID' => 7]], ['COMPONENT_ID' => 'CallMessage', 'COMPONENT_PARAMS' => ['MESSAGE_TYPE' => 'START', 'CALL_ID' => 0]]] as $params) {
            self::assertNull(CallMessage::parse($params));
        }

        $marked = CallMessage::mark([
            ['id' => 1, 'authorId' => 5, 'text' => 'Звонок завершён (1 мин)', 'params' => null],
            ['id' => 2, 'authorId' => 0, 'text' => 'Пользователь добавлен', 'params' => ['NOTIFY' => 'N']],
            ['id' => 3, 'authorId' => 5, 'text' => 'Начат звонок', 'params' => ['COMPONENT_ID' => 'CallMessage', 'COMPONENT_PARAMS' => ['MESSAGE_TYPE' => 'START', 'CALL_ID' => 7]]],
            ['id' => 4, 'authorId' => 5, 'text' => 'Без params'],
        ]);
        self::assertSame(['text', 'system', 'call', 'text'], array_column($marked, 'kind'));
        self::assertSame(['callId' => 7, 'event' => 'start'], $marked[2]['callRef']);
        self::assertSame('Звонок завершён (1 мин)', $marked[0]['text']);
        foreach ($marked as $item) {
            self::assertArrayNotHasKey('params', $item);
        }
    }

    public static function rules(): iterable
    {
        yield 'finish-author' => [['finishAuthorId' => 9], 'finish-author', 'present', 'high'];
        yield 'finish initiator param' => [['finishInitiatorId' => 9], 'finish-author', 'present', 'high'];
        yield 'call-initiator' => [['initiatorId' => 9], 'call-initiator', 'present', 'medium'];
        yield 'read-finish-at-end' => [['readers' => [9 => ['messageId' => 300, 'date' => '2026-11-12T10:30:04+03:00']]], 'read-finish-at-end', 'present', 'high'];
        yield 'message-during-call' => [['messages' => [['id' => 200, 'authorId' => 9, 'createdDate' => '2026-11-12T10:10:00+03:00']]], 'message-during-call', 'present', 'medium'];
        yield 'last-read-before-start' => [['readers' => [9 => ['messageId' => 90, 'date' => '2026-11-11T18:00:00+03:00']]], 'last-read-before-start', 'absent', 'high'];
        yield 'last-activity-before-start' => [['lastActivity' => [9 => '2026-11-12T09:00:00+03:00']], 'last-activity-before-start', 'absent', 'high'];
        yield 'last-activity-before-finish' => [['lastActivity' => [9 => '2026-11-12T10:15:00+03:00']], 'last-activity-before-finish', 'left-early', 'low'];
    }

    #[DataProvider('rules')]
    public function testEachRuleProducesItsVerdict(array $context, string $rule, string $verdict, string $confidence): void
    {
        $user = $this->infer($context)['users'][0];
        self::assertSame([$rule], array_column($user['evidence'], 'rule'));
        self::assertSame([$verdict, $confidence, false], [$user['verdict'], $user['confidence'], $user['conflict']]);
    }

    public function testRulesThatShouldNotFire(): void
    {
        $user = $this->infer([
            'readers' => [9 => ['messageId' => 300, 'date' => '2026-11-12T10:30:06+03:00']],
            'messages' => [['id' => 50, 'authorId' => 9, 'createdDate' => '2026-11-12T09:59:59+03:00'], ['id' => 51, 'authorId' => 8, 'createdDate' => '2026-11-12T10:10:00+03:00']],
            'lastActivity' => [9 => '2026-11-12T10:29:30+03:00'],
        ])['users'][0];
        self::assertSame(['unknown', null, false, []], [$user['verdict'], $user['confidence'], $user['conflict'], $user['evidence']]);
    }

    public static function verdicts(): iterable
    {
        yield 'absence with presence' => [['initiatorId' => 9, 'lastActivity' => [9 => '2026-11-12T09:00:00+03:00']], 'unknown', null, true];
        yield 'left early with presence at the end' => [['finishAuthorId' => 9, 'lastActivity' => [9 => '2026-11-12T10:15:00+03:00']], 'unknown', null, true];
        yield 'left early with presence during the call' => [['initiatorId' => 9, 'messages' => [['id' => 200, 'authorId' => 9, 'createdDate' => '2026-11-12T10:05:00+03:00']], 'lastActivity' => [9 => '2026-11-12T10:15:00+03:00']], 'left-early', 'low', false];
        yield 'present takes the highest confidence' => [['initiatorId' => 9, 'finishAuthorId' => 9], 'present', 'high', false];
        yield 'absent from two rules' => [['readers' => [9 => ['messageId' => 90, 'date' => null]], 'lastActivity' => [9 => '2026-11-12T09:00:00+03:00']], 'absent', 'high', false];
        yield 'no evidence' => [[], 'unknown', null, false];
    }

    #[DataProvider('verdicts')]
    public function testVerdictTable(array $context, string $verdict, ?string $confidence, bool $conflict): void
    {
        $user = $this->infer($context)['users'][0];
        self::assertSame([$verdict, $confidence, $conflict], [$user['verdict'], $user['confidence'], $user['conflict']]);
        if ($conflict) {
            self::assertCount(2, $user['evidence']);
        }
    }

    public function testStaleEvidenceCapsReadRulesAndSummaryCounts(): void
    {
        $result = $this->infer([
            'eligible' => [9, 8, 7, 6],
            'readers' => [9 => ['messageId' => 300, 'date' => '2026-11-12T10:30:01+03:00'], 8 => ['messageId' => 90, 'date' => null]],
            'lastActivity' => [7 => '2026-11-12T10:15:00+03:00'],
            'fresh' => false,
        ]);
        $users = array_column($result['users'], null, 'userId');
        self::assertSame(['present', 'low'], [$users[9]['verdict'], $users[9]['confidence']]);
        self::assertSame(['absent', 'low'], [$users[8]['verdict'], $users[8]['confidence']]);
        self::assertSame(['present' => 1, 'absent' => 1, 'leftEarly' => 1, 'unknown' => 1], $result['summary']);
        self::assertSame([6, 7, 8, 9], array_column($result['users'], 'userId'));
    }

    public function testSameInputsGiveSameResult(): void
    {
        $context = ['eligible' => [3, 1, 2, 1], 'initiatorId' => 1, 'readers' => [2 => ['messageId' => 300, 'date' => '2026-11-12T10:30:01+03:00']], 'lastActivity' => [3 => '2026-11-12T09:00:00+03:00']];
        self::assertSame($this->infer($context), $this->infer($context));
        self::assertSame([1, 2, 3], array_column($this->infer($context)['users'], 'userId'));
    }

    private function infer(array $context): array
    {
        return ParticipantInference::infer([
            'eligible' => [9], 'startId' => 100, 'startAt' => self::START, 'finishId' => 300, 'finishAt' => self::FINISH,
            'finishAuthorId' => null, 'finishInitiatorId' => null, 'initiatorId' => null,
            'messages' => [], 'readers' => [], 'lastActivity' => [], 'fresh' => true,
            ...$context,
        ]);
    }
}
