<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use PHPUnit\Framework\TestCase;

final class TaskTemplatesTest extends TestCase
{
    private const FIELDS = ['TITLE' => 'Типовая задача', 'CREATED_BY' => 1, 'RESPONSIBLE_ID' => 2];

    private function invoke(FakeApiTransport $api, array $args, string $stdin = ''): \Bitrix24\CLI\Tests\Support\ConsoleRun
    {
        return ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', ...$args], $stdin);
    }

    public function testShowAndMetadataKeepNativeFieldsAndSerializedValues(): void
    {
        $template = ['ID' => '139', ...self::FIELDS, 'ACCOMPLICES' => 'a:0:{}'];
        $metadata = ['TITLE' => ['type' => 'string'], 'CREATED_BY' => ['type' => 'integer']];
        foreach ([['task:template:show', '139'], ['task:template:fields:list']] as $args) {
            $api = new FakeApiTransport(static fn (string $method): ApiResponse => new ApiResponse($method === 'tasks.template.get' ? $template : $metadata));
            $run = $this->invoke($api, $args);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame($args[0] === 'task:template:show' ? $template : $metadata, array_values($run->json()['data'])[0]);
            self::assertSame($args[0] === 'task:template:show' ? ['templateId' => 139] : [], $api->calls[0]['parameters']);
            self::assertSame(1, $api->calls[0]['version']);
        }
    }

    public function testCreateAndSparseUpdateUseNativePayloadAndSdkScalarArrays(): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn (string $method): ApiResponse => new ApiResponse([$method === 'tasks.template.add' ? 139 : true]));
        $run = $this->invoke($fakeApiTransport, ['task:template:add', '--title', self::FIELDS['TITLE'], '--creator', '1', '--responsible', '2']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(['fields' => self::FIELDS], $fakeApiTransport->calls[0]['parameters']);
        self::assertSame(139, $run->json()['data']['operation']['templateId']);
        $run = $this->invoke($fakeApiTransport, ['task:template:update', '139', '--description', '']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(['templateId' => 139, 'fields' => ['DESCRIPTION' => '']], $fakeApiTransport->calls[1]['parameters']);
        self::assertSame(['DESCRIPTION'], $run->json()['data']['operation']['changedFields']);
        self::assertTrue($run->json()['data']['operation']['confirmed']);
    }

    public function testInlineFileAndStdinHaveIdenticalPayloads(): void
    {
        $fields = [...self::FIELDS, 'GROUP_ID' => 0, 'ACCOMPLICES' => [3, 4], 'AUDITORS' => [], 'ALLOW_TIME_TRACKING' => 'Y'];
        $json = json_encode($fields, JSON_THROW_ON_ERROR);
        $path = tempnam(sys_get_temp_dir(), 'template-json-');
        file_put_contents($path, $json);
        try {
            foreach ([['--fields', $json], ['--fields-file', $path], ['--fields-file', '-']] as $options) {
                $api = new FakeApiTransport(static fn (): ApiResponse => new ApiResponse([139]));
                $run = $this->invoke($api, ['task:template:add', ...$options], $json);
                self::assertSame(0, $run->status, $run->stdout);
                self::assertSame(['fields' => $fields], $api->calls[0]['parameters']);
            }
        } finally {
            unlink($path);
        }
    }

    public function testInvalidInputFailsBeforeAnyApiCall(): void
    {
        foreach ([
            ['task:template:show', '0'], ['task:template:show', '999999999999999999999999'],
            ['task:template:add', '--title', 'x'], ['task:template:update', '139'],
            ['task:template:update', '139', '--fields', '{"creatorId":1}'],
            ['task:template:update', '139', '--fields', '{"CREATED_BY":"1"}'],
            ['task:template:update', '139', '--fields', '{"ALLOW_TIME_TRACKING":true}'],
            ['task:template:update', '139', '--fields', '{"TITLE":" "}'],
            ['task:template:update', '139', '--fields', '{"TITLE":"x","TITLE":"y"}'],
            ['task:template:update', '139', '--fields', '{"TITLE":"x"}', '--title', 'y'],
            ['task:template:update', '139', '--fields', '[]'],
            ['task:template:update', '139', '--fields', '{"ID":139}'],
            ['task:template:update', '139', '--fields', '{"ACCOMPLICES":{}}'],
            ['task:template:update', '139', '--fields', '{"AUDITORS":["1"]}'],
            ['task:template:update', '139', '--fields', '{"GROUP_ID":-1}'],
            ['task:template:update', '139', '--fields', '{}', '--fields-file', '-'],
            ['task:template:delete', '139'],
        ] as $args) {
            $api = new FakeApiTransport(static fn () => throw new \LogicException('No API expected.'));
            $run = $this->invoke($api, $args);
            self::assertSame(2, $run->status, implode(' ', $args) . $run->stdout);
            self::assertSame([], $api->calls);
        }
    }

    public function testDryRunsAndStrictPolicyDeclareOnlyAdmittedRoutes(): void
    {
        foreach ([
            ['task:template:add', '--fields', json_encode(self::FIELDS, JSON_THROW_ON_ERROR)],
            ['task:template:update', '139', '--title', 'x'],
            ['task:template:delete', '139'],
        ] as $args) {
            $api = new FakeApiTransport(static fn (): ApiResponse => new ApiResponse(['ID' => '139', 'TITLE' => 'Target']));
            $run = $this->invoke($api, [...$args, '--dry-run']);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame($args[0] === 'task:template:delete' ? ['tasks.template.get'] : [], array_column($api->calls, 'method'));
            $step = $run->json()['data']['plan']['steps'][0];
            self::assertNull($step['taskId']);
            self::assertSame($args[0] === 'task:template:add' ? null : 139, $step['templateId']);
            $api->calls = [];
            $run = $this->invoke($api, [...$args, '--dry-run', '--api-policy', 'strict-rest3']);
            self::assertSame(4, $run->status, $run->stdout);
            self::assertSame('policy-denied', $run->json()['error']['code']);
            self::assertSame([], $api->calls);
        }

        foreach ([['task:template:show', '139'], ['task:template:fields:list']] as $args) {
            $api = new FakeApiTransport(static fn () => throw new \LogicException('No API expected.'));
            self::assertSame(4, $this->invoke($api, [...$args, '--api-policy', 'strict-rest3'])->status);
            self::assertSame([], $api->calls);
        }
    }

    public function testUnavailableOrMismatchedTemplatesNeverMutate(): void
    {
        foreach ([[false], ['ID' => '140']] as $response) {
            foreach ([['task:template:show', '139'], ['task:template:delete', '139', '--force'], ['task:template:delete', '139', '--dry-run']] as $args) {
                $api = new FakeApiTransport(static fn (): ApiResponse => new ApiResponse($response));
                $run = $this->invoke($api, $args);
                self::assertSame(4, $run->status, $run->stdout);
                self::assertSame(['read'], array_column($api->calls, 'effect'));
            }
        }
    }

    public function testDeleteFalseReportsPartialRegardlessOfObservedVisibility(): void
    {
        foreach ([true, false, null] as $visible) {
            $reads = 0;
            $api = new FakeApiTransport(static function (string $method) use (&$reads, $visible): ApiResponse {
                if ($method === 'tasks.template.delete') {
                    return new ApiResponse([false]);
                }

                if (++$reads === 1 || $visible === true) {
                    return new ApiResponse(['ID' => '139', 'TITLE' => 'Target']);
                }

                if ($visible === null) {
                    throw new Failure('permission-denied', 'Unavailable.');
                }

                return new ApiResponse([false]);
            });
            $run = $this->invoke($api, ['task:template:delete', '139', '--force']);
            self::assertSame(3, $run->status, $run->stdout);
            self::assertFalse($run->json()['data']['operation']['confirmed']);
            self::assertSame($visible, $run->json()['data']['operation']['visibleAfter']);
            self::assertFalse($run->json()['meta']['complete']);
            self::assertSame(['read', 'delete', 'read'], array_column($api->calls, 'effect'));
        }
    }

    public function testNegativeAndMalformedMutationRepliesAreNeverRetried(): void
    {
        foreach ([['add', [0], 4, false], ['add', ['139'], 1, true], ['add', [], 1, true], ['update', [false], 4, false], ['update', [1], 1, true], ['delete', [true], 1, true]] as [$action, $reply, $status, $unknown]) {
            $api = new FakeApiTransport(static fn (string $method): ApiResponse => new ApiResponse($method === 'tasks.template.get' ? ['ID' => '139'] : $reply));
            $args = ['task:template:' . $action];
            if ($action !== 'add') {
                $args[] = '139';
            }

            array_push($args, ...($action === 'delete' ? ['--force'] : ['--fields', json_encode(self::FIELDS, JSON_THROW_ON_ERROR)]));
            $run = $this->invoke($api, $args);
            self::assertSame($status, $run->status, $run->stdout);
            self::assertSame($unknown, $run->json()['meta']['outcomeUnknown']);
            self::assertCount(1, array_filter($api->calls, static fn (array $call): bool => $call['effect'] !== 'read'));
        }
    }

    public function testPlainAndHumanEscapeValuesWithoutChangingNativeKeys(): void
    {
        $template = ['ID' => '139', 'TITLE' => "<error>Шаблон</error>\tстрока\nещё\\"];
        foreach (['--plain', null] as $mode) {
            $api = new FakeApiTransport(static fn (): ApiResponse => new ApiResponse($template));
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['task:template:show', '139', ...($mode === null ? [] : [$mode])]);
            self::assertSame(0, $run->status);
            self::assertStringNotContainsString("\033[", $run->stdout);
            self::assertStringContainsString('TITLE', $run->stdout);
            if ($mode === '--plain') {
                self::assertSame("ID\t139\nTITLE\t<error>Шаблон</error>\\tстрока\\nещё\\\\\n", $run->stdout);
            }
        }
    }

    public function testActualSdkPreservesScalarResponsesForAllTemplateMutations(): void
    {
        $deleted = false;
        $mockHttpClient = new \Symfony\Component\HttpClient\MockHttpClient(static function (string $verb, string $url) use (&$deleted): \Symfony\Component\HttpClient\Response\MockResponse {
            $method = basename(parse_url($url, PHP_URL_PATH));
            $result = match ($method) {
                'tasks.template.add' => 139,
                'tasks.template.update' => true,
                'tasks.template.delete' => false,
                'tasks.template.get' => $deleted ? false : ['ID' => '139'],
                default => throw new \LogicException('Unexpected method.'),
            };
            if ($method === 'tasks.template.delete') {
                $deleted = true;
            }

            return new \Symfony\Component\HttpClient\Response\MockResponse(json_encode(['result' => $result, 'time' => []], JSON_THROW_ON_ERROR));
        });
        $runtimeState = new \Bitrix24\CLI\Application\RuntimeState();
        $b24ClientProvider = new \Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider(new \Bitrix24\CLI\Tests\Support\SpyConnectionResolver(), $runtimeState, $mockHttpClient);
        $rest1TaskTemplateAdapter = new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskTemplateAdapter(new \Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport($b24ClientProvider, $runtimeState));
        self::assertSame(139, $rest1TaskTemplateAdapter->add(self::FIELDS));
        $rest1TaskTemplateAdapter->update(139, ['DESCRIPTION' => 'x']);
        self::assertSame(['ID' => '139'], $rest1TaskTemplateAdapter->get(139));
        $rest1TaskTemplateAdapter->delete(139);
        self::assertNull($rest1TaskTemplateAdapter->get(139));
        self::assertSame(['write', 'write', 'read', 'delete', 'read'], array_column($runtimeState->calls, 'effect'));
    }

    public function testPlainMetadataMutationAndPlanUseDocumentedColumns(): void
    {
        foreach ([
            [['task:template:fields:list'], ['TITLE' => ['type' => 'string']], "TITLE\t{\"type\":\"string\"}\n"],
            [['task:template:update', '139', '--description', 'x'], [true], "139\tupdate\t[\"DESCRIPTION\"]\ttrue\n"],
        ] as [$args, $reply, $expected]) {
            $api = new FakeApiTransport(static fn (): ApiResponse => new ApiResponse($reply));
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--plain', ...$args]);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame($expected, $run->stdout);
        }

        $api = new FakeApiTransport(static fn () => throw new \LogicException('No API expected.'));
        $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--plain', 'task:template:update', '139', '--description', 'x', '--dry-run']);
        self::assertSame(0, $run->status);
        self::assertSame("\t1\ttasks.template.update\t1.0\twrite\t{\"DESCRIPTION\":\"x\"}\t139\n", $run->stdout);
    }

    public function testInvalidMetadataDoesNotBecomeAnEmptySuccessfulSchema(): void
    {
        foreach ([[false], [], ['TITLE' => 'string']] as $reply) {
            $api = new FakeApiTransport(static fn (): ApiResponse => new ApiResponse($reply));
            $run = $this->invoke($api, ['task:template:fields:list']);
            self::assertSame(1, $run->status);
            self::assertSame('api-error', $run->json()['error']['code']);
        }
    }
}
