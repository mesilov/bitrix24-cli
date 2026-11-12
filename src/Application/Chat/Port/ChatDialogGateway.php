<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat\Port;

interface ChatDialogGateway
{
    /**
     * Reads chat metadata through im.dialog.get without changing read state.
     *
     * @return array<string, mixed> native im.dialog.get fields
     */
    public function dialog(string $dialogId): array;
}
