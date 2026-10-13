<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Port\DiskUploadGateway;

final readonly class Rest1DiskUploadAdapter implements DiskUploadGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function upload(int $folderId, string $name, string $base64): array
    {
        // SDK Folder::uploadFile parameter shape, through the CLI's guarded transport.
        $result = $this->api->call('disk.folder.uploadFile', 1, [
            'id' => $folderId, 'data' => ['NAME' => $name], 'fileContent' => [$name, $base64], 'generateUniqueName' => true,
        ], 'write')->result;
        $id = $result['ID'] ?? null;
        $returnedName = $result['NAME'] ?? null;
        if ((!is_int($id) && (!is_string($id) || !ctype_digit($id))) || (int) $id < 1 || (string) (int) $id !== ltrim((string) $id, '0')
            || !is_string($returnedName) || $returnedName === '' || !mb_check_encoding($returnedName, 'UTF-8') || mb_strlen($returnedName, 'UTF-8') > 255 || preg_match('~[\\\\/\p{Cc}]~u', $returnedName) === 1) {
            throw new Failure('api-error', 'Disk upload returned an unusable file confirmation; inspect the selected folder before retrying.', 1, [], true);
        }

        return ['ID' => (int) $id, 'NAME' => $returnedName];
    }
}
