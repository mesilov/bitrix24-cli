<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

/**
 * The comment feed of the old task card (before the task chat). Read only; IDs are not chat message IDs.
 */
interface LegacyCommentGateway
{
    /**
     * One page ordered by comment ID; $cursor excludes it and everything before it in $direction.
     *
     * @param 'asc'|'desc' $direction
     * @return list<array{id: int, authorId: ?int, authorName: ?string, createdDate: ?string, text: ?string, attachments: list<array{attachmentId: int, fileId: ?int, name: ?string, size: ?int}>}>
     */
    public function page(int $taskId, string $direction, ?int $cursor): array;
}
