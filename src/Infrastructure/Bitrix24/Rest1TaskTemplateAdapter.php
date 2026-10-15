<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Port\TaskTemplateGateway;

final readonly class Rest1TaskTemplateAdapter implements TaskTemplateGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function get(int $id): ?array
    {
        $result = $this->api->call('tasks.template.get', 1, ['templateId' => $id])->result;
        if ($result === [false]) {
            return null;
        }

        $actual = $result['ID'] ?? null;
        if ((!is_int($actual) && (!is_string($actual) || !ctype_digit($actual))) || (string) $actual !== (string) $id) {
            throw Failure::binding('Bitrix24 did not return the selected template ID.');
        }

        return $result;
    }

    public function fields(): array
    {
        $fields = $this->api->call('tasks.template.fields', 1, [])->result;
        if ($fields === [] || array_is_list($fields) || array_any($fields, static fn ($value): bool => !is_array($value))) {
            throw new Failure('api-error', 'Bitrix24 returned invalid template field metadata.');
        }

        return $fields;
    }

    public function add(array $fields): int
    {
        $result = $this->api->call('tasks.template.add', 1, ['fields' => $fields], 'write')->result;
        $id = array_is_list($result) && count($result) === 1 ? $result[0] : null;
        if ($id === 0) {
            throw new Failure('template-write-rejected', 'Bitrix24 did not create the template; verify required fields and permissions.', 4);
        }

        if (!is_int($id) || $id < 1) {
            throw new Failure('api-error', 'Bitrix24 did not confirm template creation; inspect the portal before retrying.', 1, [], true);
        }

        return $id;
    }

    public function update(int $id, array $fields): void
    {
        $result = $this->api->call('tasks.template.update', 1, ['templateId' => $id, 'fields' => $fields], 'write')->result;
        if ($result === [false]) {
            throw new Failure('template-write-rejected', 'The template is missing or cannot be edited by this connection.', 4);
        }

        if ($result !== [true]) {
            throw new Failure('api-error', 'Bitrix24 did not confirm the template update; inspect it before retrying.', 1, [], true);
        }
    }

    public function delete(int $id): void
    {
        if ($this->api->call('tasks.template.delete', 1, ['templateId' => $id], 'delete')->result !== [false]) {
            throw new Failure('api-error', 'Unexpected template deletion response; inspect the portal before retrying.', 1, [], true);
        }
    }
}
