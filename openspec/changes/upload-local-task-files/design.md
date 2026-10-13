# Design

## Context

Мотивация и объём описаны в proposal.md. На dev `6d9fe39` AttachTaskFilesCommand принимает TASK_ID и repeatable --file-id. TaskInputMapper строит AttachTaskFilesRequest(taskId, fileIds); handler создаёт PreparedOperation и выполняет REST3 attach с ExecutionLedger. Runtime policy использует CommandCatalog::ROUTES; ApiTransport вызывает Core SDK с явной ApiVersion. ResultPresenter поддерживает mutation rows и общий data/meta/error envelope, API versions и partial/interrupted. Метаданные плана сейчас видны пользователю: Base64 в Plan::fields недопустим.

`improve-task-cli-dx` исключает local upload (O8), поэтому данный change владеет новым режимом. Исходные 30 команд и --file-id контракт не меняются; delta specs обновляют scoped Disk admission и исключение бинарного --file из текстового stdin правила.

## Goals / Non-Goals

Цель — один явный вызов CLI для передачи локального файла и привязки, с честным результатом двух записей. Данные пользователя не выводятся как payload.

Не добавляются Disk namespace, discovery/creation/default folder, автоматические ACL, background upload, multipart/chunking, stdin и несколько локальных файлов за один вызов. Папка задаётся пользователем явно; её доступность и права на task attachment проверяются сервером. Предварительное чтение задачи/папки не считается гарантией возможности записать и в этот scope не входит.

## Decisions

### 1. Два режима существующей команды

Добавить `--file PATH`, `--folder-id ID`, `--name NAME` в AttachTaskFilesCommand; режим `--file-id` и локальный upload взаимоисключающие. Input mapper валидирует все сочетания и IDs до API. Request явно хранит только один режим; routes принимает request и объявляет Disk route только для локального режима. Добавление отдельной команды task:file:upload потребовало бы нового каталога/счётчика и integration класса; текущий issue допускает объединённую команду, поэтому расширяем существующую.

Примеры предлагаемого интерфейса (пока не реализованы):

```sh
b24cli task:file:attach 123 --file './Документы/Отчёт.pdf' --folder-id 456
b24cli task:file:attach 123 --file './report.pdf' --folder-id 456 --name 'Отчёт.pdf' --dry-run --json
b24cli task:file:attach 123 --file-id 789
```

### 2. Ограниченная прямая загрузка

Использовать существующий ApiTransport/Core REST1, без внешнего HTTP клиента: `disk.folder.uploadFile` с `id` (folder ID), `data.NAME`, `fileContent=[name,base64]`, `generateUniqueName=true`. Optional rights не передавать; применяются серверные правила доступа, их практическое действие проверяется в live cases. ID результата передавать в REST3 `tasks.task.file.attach` как `taskId` и `fileIds=[ID]`. Не подменять ID внутренним FILE_ID.

Выделить application Disk upload port и Rest1Disk adapter; не расширять TaskGateway Disk методами. Handler управляет orchestration, adapter знает API форму. Bootstrap регистрирует новый port/adapter тем же способом, что остальные services.

Прямая Base64 загрузка избегает отдельного uploadUrl bearer flow/multipart транспорта. Цена — рост памяти и размера payload. Установить фиксированный лимит CLI **10 MiB (10485760 bytes)**; это проектное решение, не заявленный лимит Bitrix24. Проверить local regular/readable файл, размер и имя, затем читать максимум limit+1 с защитой от специальных файлов/подмены. Чтение exact bytes/кодирование происходит до первой записи и только при реальном execute; dry-run достаточно безопасной локальной проверки/статистики. Reject URLs/wrappers/`-`; Windows drive paths не путать с URI schemes. Unicode/space paths и zero-byte файла не менять. Symlink допускается только если разрешается в читаемый regular local file; повторная проверка открытого handle/fstat защищает от подмены на FIFO/роста.

Имя — --name либо basename, UTF-8, непустое, не . или .., максимум 255 символов, без обоих separators/control characters. Использовать одинаковый NAME и fileContent[0]; unique name запрашивается всегда, итоговое NAME берётся из результата сервера.

### 3. Preview и API policy

CommandCatalog::ROUTES включает допустимую пару disk.folder.uploadFile/1; routes(request) выбирает её только для local mode. ApiPolicyGuard проверяет полный mixed plan до credentials/API; strict-rest3 возвращает existing policy-denied/exit4, в том числе при dry-run. --file-id остаётся REST3-only.

PreparedOperation содержит безопасные descriptors: taskId, folder id (API `id`), NAME, sizeBytes, generateUniqueName, два метода/API versions и явную ссылку attach на Disk ID из upload. Контейнеры preview/result служебные; реальные API fields всегда id/data.NAME/fileContent/taskId/fileIds. Никаких payload bytes/Base64/SDK objects/download URL в plan или выводе. Dry-run не создаёт API client и не читает credentials, не обещает права/квоту/успех.

### 4. Ledger и восстановление

Успех: два mutation rows с action upload/attach, taskId, resourceId=Disk ID, confirmed=true, changedFields; local mode дополнительно сохраняет фактическое NAME/sizeBytes без URL. meta ledger описывает оба шага; schemaVersion=1 и mutation TSV columns сохраняются. Изменения shared presenter/ledger допустимы только если нужны для возврата динамического Disk ID, без изменения других команд.

Upload server denial: attach skipped, exit1 без подтверждённого upload. После отправки upload потеря ответа либо malformed success без валидного Disk ID: upload unknown/outcomeUnknown, attach skipped, без retry. Attach может отказать после успешного upload; это exit3 с upload row/ID, complete=false, attach failed. При потере attach ответа — attach unknown/outcomeUnknown и проверка привязки перед ручным повтором. Подтверждённый Disk ID пригоден для `task:file:attach TASK_ID --file-id ID` без второй загрузки. Никакого implicit rollback/delete: orphan остаётся видимым результатом.

Перед каждым request и между шагами проверять RuntimeState cancellation. Ctrl-C после upload возвращает 130 с подтверждённым Disk ID и skipped/unknown attach по факту отправки. Не терять confirmed upload в exception path. ACK attach подтверждает метод, не независимое членство файла в задаче.

### 5. Проверки и источники

Offline tests проходят через production bootstrap с fake ApiTransport: modes/IDs/path/name/limit, exact payload, Disk ID vs FILE_ID, dynamic policy, no-credentials preview, binary preservation, upload deny/malformed/timeout, attach deny/unknown, cancellation и secret/content non-disclosure во всех форматах. Existing --file-id contract и 30-command registration остаются в regression suite.

Live case добавить в существующий AttachTaskFilesCommandTest, group command:task:file:attach/happy-path. Fixtures и cleanup — SDK, disposable задача/папка/локальный файл; cleanup только собственных fixtures и только с известными ID. Disk file readback подтверждает сохранённый NAME/SIZE/PARENT_ID, attach ACK отдельно от membership. Новое разрешение `disk` на webhook требуется для local mode; missing scope/недоступная папка/attachment права — отдельные негативные live cases при доступных fixtures. Unit/integration skip/CI не считаются portal evidence.

Официальный MCP доступен: `codex mcp get bitrix24_dev --json`, реальный bitrix-search и details проверены 13.10.2026:

| Контракт | Официальный источник | Граница |
|---|---|---|
| Upload REST1 | https://apidocs.bitrix24.ru/api-reference/disk/folder/disk-folder-upload-file.html | id, data.NAME <=255, fileContent, boolean generateUniqueName, file result/ID; direct Base64 route |
| Attach REST3 | https://apidocs.bitrix24.ru/api-reference/tasks/rest-v3/tasks-task-file-attach.html | taskId integer, fileIds array, boolean success |
| Disk readback | https://apidocs.bitrix24.ru/api-reference/disk/file/disk-file-get.html | id; result ID/NAME/SIZE/PARENT_ID, включая numeric strings в примере; не доказывает task membership |
| Folder metadata | https://apidocs.bitrix24.ru/api-reference/disk/folder/disk-folder-get.html | id/TYPE folder; справочный контракт, production route не добавляется |

Размер CLI, доступность квоты, Unicode/collision behavior и практическая ACL семантика не подтверждены одним чтением документации; фиксируются отдельно при live acceptance.

## Risks / Trade-offs

- Base64 payload/memory overhead → bounded 10 MiB и отказ до первой записи; большие файлы требуют отдельного change.
- Task attachment denial после успешной загрузки → durable Disk ID, partial ledger, ручная привязка существующего файла.
- Потерянный upload response → outcomeUnknown без автоматического повторения; проверить целевую папку в портале.
- Не хватает disk scope/квоты/ACL → безопасная серверная ошибка, без смены профиля и расширения rights.
- Подмена/рост local file → regular-file checks и bounded read handle до mutation, без блокирующих pipe reads.
- Shared runtime затрагивает другие команды → полный unit/lint regression и сохранение existing ID mode.

## Delivery

После отдельного запроса apply выполнить задачи и verify, свежий fetch/merge origin/dev и PR в dev. Planning PR не закрывает issue. После подтверждённого implementation merge/закрытия issue — синхронизация/архив OpenSpec, доставка архива через dev PR и cleanup worktree по maintainer. Это не release/publication proof.
