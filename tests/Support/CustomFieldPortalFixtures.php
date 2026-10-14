<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

trait CustomFieldPortalFixtures
{
    private function customSchema(): array
    {
        $fields = $this->api->call('tasks.task.getFields', 1, [])->result['fields'];
        $byTitle = [];
        foreach ($fields as $code => $field) {
            if (str_starts_with($code, 'UF_AUTO_')) {
                $byTitle[$field['title'] ?? ''] = $code;
            }
        }

        foreach (['string_req', 'int_req', 'string', 'string_multiple'] as $title) {
            if (!isset($byTitle[$title])) {
                self::markTestSkipped('Custom portal fixture requires existing string_req/int_req/string/string_multiple fields.');
            }
        }

        return $byTitle;
    }

    private function customValues(array $schema, string $value): array
    {
        return [$schema['string_req'] => 'required-' . $value, $schema['int_req'] => 58, $schema['string'] => $value, $schema['string_multiple'] => ['a-' . $value, 'b-' . $value]];
    }

    private function customCard(int $id): array
    {
        return array_column($this->card($id, ['id', 'userFields.key', 'userFields.value'])['userFields'], 'value', 'key');
    }

    private function receipt(string $scenario, array $data): void
    {
        file_put_contents($this->root() . '/var/cache/issue58-cli-acceptance.jsonl', json_encode(['scenario' => $scenario, 'actingUserId' => $this->userId, ...$data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }
}
