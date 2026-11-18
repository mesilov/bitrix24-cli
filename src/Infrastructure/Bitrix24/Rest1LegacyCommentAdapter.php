<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Port\LegacyCommentGateway;

/**
 * REST 1.0 task.commentitem.getlist (deprecated since tasks 25.700.0, still documented).
 * Observed on the portal (research.md): `start` is ignored, FILTER without ORDER is rejected,
 * and attachment URLs carry an auth token, so only safe attachment fields leave this class.
 */
final readonly class Rest1LegacyCommentAdapter implements LegacyCommentGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function page(int $taskId, string $direction, ?int $cursor): array
    {
        $params = ['TASKID' => $taskId, 'ORDER' => ['ID' => $direction]];
        if ($cursor !== null) {
            $params['FILTER'] = [($direction === 'asc' ? '>' : '<') . 'ID' => $cursor];
        }

        $result = $this->api->call('task.commentitem.getlist', 1, $params)->result;
        if (!array_is_list($result)) {
            throw new Failure('api-error', 'The API returned an unexpected legacy comment list.');
        }

        return array_map($this->comment(...), $result);
    }

    private function comment(mixed $row): array
    {
        $id = is_array($row) ? $this->int($row['ID'] ?? null) : null;
        if ($id === null) {
            throw new Failure('api-error', 'The API returned a legacy comment without a valid ID.');
        }

        $attachments = [];
        foreach (is_array($row['ATTACHED_OBJECTS'] ?? null) ? $row['ATTACHED_OBJECTS'] : [] as $key => $file) {
            if (!is_array($file)) {
                continue;
            }

            // DOWNLOAD_URL/VIEW_URL contain auth[ap]; they are never copied.
            $attachments[] = [
                'attachmentId' => $this->int($file['ATTACHMENT_ID'] ?? $key) ?? 0,
                'fileId' => $this->int($file['FILE_ID'] ?? null),
                'name' => is_string($file['NAME'] ?? null) ? $file['NAME'] : null,
                'size' => $this->int($file['SIZE'] ?? null),
            ];
        }

        return [
            'id' => $id,
            'authorId' => $this->int($row['AUTHOR_ID'] ?? null),
            'authorName' => is_string($row['AUTHOR_NAME'] ?? null) ? $row['AUTHOR_NAME'] : null,
            'createdDate' => is_string($row['POST_DATE'] ?? null) ? $row['POST_DATE'] : null,
            'text' => is_string($row['POST_MESSAGE'] ?? null) ? $row['POST_MESSAGE'] : null,
            'attachments' => $attachments,
        ];
    }

    private function int(mixed $value): ?int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) && strlen($value) < 19 ? (int) $value : null;
    }
}
