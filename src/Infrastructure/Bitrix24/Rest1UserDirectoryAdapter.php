<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\User\Port\UserDirectoryGateway;
use Bitrix24\CLI\Application\User\UserPage;
use Bitrix24\CLI\Application\User\UserRecord;

final readonly class Rest1UserDirectoryAdapter implements UserDirectoryGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function current(): array
    {
        return UserRecord::normalize($this->api->call('user.current', 1, [])->result);
    }

    public function page(array $filter, int $start): UserPage
    {
        $response = $this->api->call('user.get', 1, ['FILTER' => $filter, 'select' => UserRecord::FIELDS, 'sort' => 'ID', 'order' => 'ASC', 'start' => $start]);
        if (!array_is_list($response->result) || count($response->result) > 50 || ($response->next !== null && ($response->next <= $start || $response->result === [])) || ($response->total !== null && $response->total < 0)) {
            throw new Failure('api-error', 'The directory returned an unsupported page or pagination cursor.');
        }

        $items = [];
        foreach ($response->result as $row) {
            if (!is_array($row)) {
                throw new Failure('api-error', 'The directory returned an unsupported user row.');
            }

            $user = UserRecord::normalize($row);
            if (isset($items[$user['ID']])) {
                throw new Failure('api-error', 'The directory returned duplicate user IDs.');
            }

            $items[$user['ID']] = $user;
        }

        return new UserPage(array_values($items), $response->next, $response->total);
    }

    public function byId(int $id): ?array
    {
        $page = $this->page(['ID' => $id], 0);
        if ($page->next !== null || count($page->items) > 1 || ($page->items !== [] && (int) $page->items[0]['ID'] !== $id)) {
            throw new Failure('api-error', 'The directory returned an unexpected user ID.');
        }

        return $page->items[0] ?? null;
    }
}
