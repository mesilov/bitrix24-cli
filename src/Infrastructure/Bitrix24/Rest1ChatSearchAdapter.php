<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Chat\Port\ChatSearchGateway;
use Bitrix24\CLI\Application\Failure;

final readonly class Rest1ChatSearchAdapter implements ChatSearchGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function chats(string $find, int $offset, int $limit): array
    {
        $response = $this->api->call('im.search.chat.list', 1, ['FIND' => $find, 'OFFSET' => $offset, 'LIMIT' => $limit]);
        $items = [];
        foreach ($this->rows($response, 'im.search.chat.list') as $row) {
            $items[] = ['id' => $this->id($row['id'] ?? null, 'im.search.chat.list'), 'name' => $this->name($row['name'] ?? null, 'im.search.chat.list'), 'type' => is_string($row['type'] ?? null) ? $row['type'] : ''];
        }

        return ['items' => $items, 'next' => $response->next];
    }

    public function groups(string $find, int $start): array
    {
        $response = $this->api->call('sonet_group.get', 1, ['FILTER' => ['%NAME' => $find], 'ORDER' => ['ID' => 'ASC'], 'start' => $start]);
        $items = [];
        foreach ($this->rows($response, 'sonet_group.get') as $row) {
            $items[] = ['id' => $this->id($row['ID'] ?? null, 'sonet_group.get'), 'name' => $this->name($row['NAME'] ?? null, 'sonet_group.get'), 'project' => ($row['PROJECT'] ?? null) === 'Y'];
        }

        return ['items' => $items, 'next' => $response->next];
    }

    /** @return list<array<string, mixed>> */
    private function rows(ApiResponse $response, string $method): array
    {
        if (!array_is_list($response->result) || array_any($response->result, static fn (mixed $row): bool => !is_array($row))) {
            throw new Failure('api-error', $method . ' returned a malformed result list.');
        }

        return $response->result;
    }

    private function id(mixed $value, string $method): int
    {
        if ((!is_int($value) && (!is_string($value) || !ctype_digit($value))) || (int) $value < 1) {
            throw new Failure('api-error', $method . ' returned a row without a valid ID.');
        }

        return (int) $value;
    }

    private function name(mixed $value, string $method): string
    {
        if (!is_string($value)) {
            throw new Failure('api-error', $method . ' returned a row without a name.');
        }

        return $value;
    }
}
