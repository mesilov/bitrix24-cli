<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\AttachTaskFilesRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class AttachTaskFilesHandler
{
    public function __construct(private TaskGateway $tasks, private \Bitrix24\CLI\Application\Task\Port\DiskUploadGateway $disk, private \Bitrix24\CLI\Application\Task\Port\LocalFileGateway $files, private \Bitrix24\CLI\Application\RuntimeState $state)
    {
    }

    public function routes(AttachTaskFilesRequest $request): array
    {
        return [...($request->file instanceof \Bitrix24\CLI\Application\Task\LocalFile ? [new Route('disk.folder.uploadFile', 1, 'write')] : []), new Route('tasks.task.file.attach', 3, 'write')];
    }

    public function prepare(AttachTaskFilesRequest $request): PreparedOperation
    {
        if ($request->file instanceof \Bitrix24\CLI\Application\Task\LocalFile) {
            return $this->local($request);
        }

        $steps = [];
        foreach ($request->fileIds as $index => $id) {
            $steps[] = Plan::step($request->taskId, 'tasks.task.file.attach', 3, 'write', ['fileIds' => [$id]], $index + 1);
        }

        return new PreparedOperation($steps, function () use ($request): OperationResult {
            $executionLedger = new \Bitrix24\CLI\Application\ExecutionLedger();
            foreach ($request->fileIds as $index => $id) {
                try {
                    $this->tasks->attach($request->taskId, $id);
                    $executionLedger->confirm(['taskId' => $request->taskId, 'resourceId' => $id, 'action' => 'attach', 'changedFields' => ['fileIds'], 'confirmed' => true]);
                } catch (Failure $failure) {
                    return $executionLedger->stop($id, array_slice($request->fileIds, $index + 1), $failure);
                }
            }

            return $executionLedger->result();
        }, 'files in task ' . $request->taskId);
    }

    private function local(AttachTaskFilesRequest $request): PreparedOperation
    {
        $file = $request->file;
        $folderId = $request->folderId;
        if (!$file instanceof \Bitrix24\CLI\Application\Task\LocalFile || $folderId === null) {
            throw Failure::usage('Local attachment needs --file and --folder-id.');
        }

        $steps = [
            Plan::step($request->taskId, 'disk.folder.uploadFile', 1, 'write', ['id' => $folderId, 'data' => ['NAME' => $file->name], 'sizeBytes' => $file->sizeBytes, 'maxUploadBytes' => $file->maxUploadBytes, 'generateUniqueName' => true]),
            Plan::step($request->taskId, 'tasks.task.file.attach', 3, 'write', ['taskId' => $request->taskId, 'fileIds' => ['<Disk ID returned by upload>']], 2),
        ];
        return new PreparedOperation($steps, function () use ($request, $file, $folderId): OperationResult {
            $this->state->checkCancellation();
            $base64 = $this->files->encode($file);
            $this->state->checkCancellation();
            try {
                $uploaded = $this->disk->upload($folderId, $file->name, $base64);
            } catch (Failure $failure) {
                throw new Failure($failure->errorCode, $failure->getMessage() . ' Inspect the selected Disk folder before retrying an unknown upload.', $failure->exitStatus, ['ledger' => [
                    ['action' => 'upload', 'resourceId' => null, 'status' => $failure->outcomeUnknown ? 'unknown' : ($failure->exitStatus === 130 ? 'skipped' : 'failed')],
                    ['action' => 'attach', 'resourceId' => null, 'status' => 'skipped'],
                ]], $failure->outcomeUnknown);
            }

            $upload = ['taskId' => $request->taskId, 'resourceId' => $uploaded['ID'], 'action' => 'upload', 'changedFields' => ['NAME', 'fileContent'], 'confirmed' => true, 'NAME' => $uploaded['NAME'], 'sizeBytes' => $file->sizeBytes];
            $ledger = [[...$upload, 'status' => 'confirmed']];
            try {
                $this->state->checkCancellation();
                $this->tasks->attach($request->taskId, $uploaded['ID']);
            } catch (Failure $failure) {
                $ledger[] = ['action' => 'attach', 'resourceId' => $uploaded['ID'], 'status' => $failure->outcomeUnknown ? 'unknown' : ($failure->exitStatus === 130 ? 'skipped' : 'failed')];
                $recovery = $failure->outcomeUnknown
                    ? 'Inspect task attachment before retrying; do not upload the file again.'
                    : 'After checking the target and permissions, attach the uploaded file with task:file:attach ' . $request->taskId . ' --file-id ' . $uploaded['ID'] . '.';
                return new OperationResult('mutation', ['items' => [$upload]], ['ledger' => $ledger, 'complete' => false, 'outcomeUnknown' => $failure->outcomeUnknown, 'reason' => $failure->getMessage() . ' ' . $recovery], $failure->exitStatus === 130 ? 130 : 3);
            }

            $attach = ['taskId' => $request->taskId, 'resourceId' => $uploaded['ID'], 'action' => 'attach', 'changedFields' => ['fileIds'], 'confirmed' => true];
            return new OperationResult('mutation', ['items' => [$upload, $attach]], ['ledger' => [...$ledger, [...$attach, 'status' => 'confirmed']], 'complete' => true, 'outcomeUnknown' => false]);
        }, 'local file in folder ' . $folderId . ' for task ' . $request->taskId);
    }
}
