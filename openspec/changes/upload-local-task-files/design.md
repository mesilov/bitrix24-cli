# Design

## Context

Мотивация и объём описаны в proposal.md. На dev `6d9fe39` AttachTaskFilesCommand принимает TASK_ID и repeatable --file-id. TaskInputMapper строит AttachTaskFilesRequest(taskId, fileIds); handler создаёт PreparedOperation и выполняет REST3 attach с ExecutionLedger. Runtime policy использует CommandCatalog::ROUTES; ApiTransport вызывает Core SDK с явной ApiVersion. ResultPresenter поддерживает mutation rows и общий data/meta/error envelope, API versions и partial/interrupted. Метаданные плана сейчас видны пользователю: Base64 в Plan::fields недопустим.

`improve-task-cli-dx` исключает local upload (O8), поэтому данный change владеет новым режимом. Текущий каталог из 34 task команд (с результатами из актуальной dev) и --file-id контракт не меняются; delta specs обновляют scoped Disk admission и исключение бинарного --file из текстового stdin правила.

## Goals / Non-Goals

Цель — один явный вызов CLI для передачи локального файла и привязки, с честным результатом двух записей. Данные пользователя не выводятся как payload.

Не добавляются Disk namespace, discovery/creation/default folder, автоматические ACL, background upload, multipart, stdin и несколько локальных файлов за один вызов. Папка задаётся пользователем явно; её доступность и права на task attachment проверяются сервером. Предварительное чтение задачи/папки не считается гарантией возможности записать и в этот scope не входит.

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

Приоритет — переиспользовать подходящие SDK upload services/results за application port с сохранением ApiTransport/runtime boundaries; если это невозможно без нарушения policy/accounting/cancellation, использовать существующий ApiTransport/Core REST1 и документировать причину. Без внешнего HTTP клиента: `disk.folder.uploadFile` с `id` (folder ID), `data.NAME`, `fileContent=[name,base64]`, `generateUniqueName=true`. Optional rights не передавать; применяются серверные правила доступа, их практическое действие проверяется в live cases. ID результата передавать в REST3 `tasks.task.file.attach` как `taskId` и `fileIds=[ID]`. Не подменять ID внутренним FILE_ID.

Выделить application Disk upload port; adapter SHALL оборачивать готовые SDK механизмы, когда они совместимы с CLI контрактом, либо только недостающую Core REST1 часть с явным обоснованием. Не расширять TaskGateway Disk методами. Handler управляет orchestration, adapter знает API форму. Bootstrap регистрирует новый port/adapter тем же способом, что остальные services.

### 2a. Symfony Filesystem и обязательная оценка SDK reuse

Локальные файловые операции SHALL проходить через внедрённую абстракцию на базе `Symfony\Component\Filesystem\Filesystem` и `Path`; не создавать параллельный набор утилит для exists/path operations. Исключения Filesystem и SDK отображать в существующие usage/runtime errors без содержимого файла. Низкоуровневый bounded stream/handle код допустим только внутри этой абстракции для проверок/лимита, которые готовые APIs не обеспечивают. `symfony/filesystem` уже зафиксирован транзитивно как v8.1.6; при прямом использовании в CLI объявить его прямой зависимостью, проверить production PHAR и Composer/license checks без ненужного обновления SDK.

Перед реализацией local source/upload adapter обязательно сравнить публичные механизмы **версии из composer.lock**, зафиксировать таблицу «кандидат → reuse/adaptation/reject → причина → тест» в этом design. Приоритет: переиспользовать готовый API; если ограничение мешает — переиспользовать совместимую часть/подход; собственный код писать только для зафиксированного gap. Не копировать vendor код целиком и не менять сам SDK в этом issue.

Первичное source review 13.10.2026, SDK 3.7.0 (`8ebd4c154d5557db949b0196825f347a3a6c5bf0`):

- [`Infrastructure\Filesystem\Base64Encoder`](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Infrastructure/Filesystem/Base64Encoder.php) внедряет Symfony Filesystem и Symfony Mime encoder. `encodeFile()` проверяет exists, но читает целиком через file_get_contents; `encodeString()` может переиспользоваться после bounded read. Проверить round-trip точных бинарных байтов/MIME Base64 формат и безопасный logger; полный encodeFile не заменяет ограничения CLI.
- [`Disk\Folder\Service\Folder::uploadFile()`](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Disk/Folder/Service/Folder.php) принимает id/data/fileContent/generateUniqueName/rights и строит upload params. Оценить использование сервиса/подхода формирования payload. SDK использует имя `disk.folder.uploadfile` и Core default version: прямое переиспользование требует явного согласования с catalog route `disk.folder.uploadFile`/REST1 и existing policy/accounting/cancellation, без обхода guard или скрытого запроса.
- [`UploadedFileResult`](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Disk/Folder/Result/UploadedFileResult.php) предоставляет getId()/getFile(); getId делает int cast, поэтому CLI всё равно обязан валидировать positive Disk ID/форму ответа до attach.
- [`Disk Folder tests`](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/tests/Unit/Services/Disk/Folder/Service/FolderTest.php) и SDK integration fixtures/cleanup исследовать как источники подходов; не считать наличие SDK теста доказательством CLI acceptance.
- Symfony [`Filesystem::readFile()`](https://github.com/symfony/filesystem/blob/7599ebb855fede59413ddb7f15198d67bfcae7ba/Filesystem.php) читает файл целиком, поэтому сам по себе не даёт bounded read. [`Path`](https://github.com/symfony/filesystem/blob/7599ebb855fede59413ddb7f15198d67bfcae7ba/Path.php) предоставляет normalize/isLocal/isAbsolute; проверить локальность до преобразований, не менять смысл symlink/`..` и Windows путей. Context7 использован для документации; методы сверены с source именно locked v8.1.6, без переноса будущих APIs.

Решение реализации 13.10.2026:

| Кандидат | Решение и причина | Проверка |
|---|---|---|
| Symfony Filesystem/Path v8.1.6 | Reuse: внедрённый LocalFileSource, exists и OS-aware normalize/isLocal; прямой require ^8.0, locked version не изменён | LocalFileUploadTest: Unicode/space/local paths, invalid paths, unreadable/directory/symlink; packaging smoke preview |
| SDK Base64Encoder::encodeString | Reuse готового SDK API с Symfony Mime encoder и NullLogger после bounded read | Exact binary round-trip включая NUL/0xff, zero и 500 MB; API payload не попадает в stdout |
| SDK encodeFile и Symfony readFile | Не используем чтение целиком: нет ограничения размера/handle identity. Собственное чтение limit+1/fstat изолировано внутри LocalFileSource | Growth/replacement/deletion/limit+1/regular file checks до upload |
| Folder::uploadFile | Переиспользован подход payload через Rest1DiskUploadAdapter/ApiTransport; прямой wrapper обходит RuntimeState call accounting/cancellation и передаёт lower-case route/default version | Контракт точных id/data.NAME/fileContent/generateUniqueName, method/version и strict-rest3; live mixed calls |
| UploadedFileResult | Переиспользован контракт ID/NAME, без unchecked getId int cast; возвращаются только эти поля | Positive numeric-string ID/overflow/malformed/FILE_ID tests, unknown + skipped attach |
| SDK TemporaryDiskFixture | Переиспользован подход уникальных owned child folder, known-ID cleanup и readback; сам test helper отсутствует в Composer distribution | LiveTaskCommandTestCase: owned folder/file/task IDs, cleanup receipt; 5 portal cases |

Все source references выше относятся к версии composer.lock. При использовании текущих SDK API не копируется vendor код; state/вывод остаются CLI ответственностью.

Прямая Base64 загрузка избегает отдельного uploadUrl bearer flow/multipart транспорта. До 10 MiB используется готовое SDK encodeString; большие файлы кодируются тем же SDK порциями и отправляются потоковым JSON body через Symfony HttpClient, без полной строки Base64 в памяти. Default лимит CLI — **500 MB (500000000 bytes)**; positive integer `maxUploadBytes` в runtime --config JSON имеет приоритет и может быть ниже/выше default; это проектное решение, не заявленный лимит Bitrix24. Проверить local regular/readable файл, размер и имя, читать bounded chunks с cumulative size/identity checks. Проверка metadata происходит до записи; чтение/кодирование больших файлов происходит при отправке body и только при реальном execute; dry-run достаточно безопасной локальной проверки/статистики. Reject URLs/wrappers/`-`; Windows drive paths не путать с URI schemes. Unicode/space paths и zero-byte файла не менять. Symlink допускается только если разрешается в читаемый regular local file; повторная проверка открытого handle/fstat защищает от подмены на FIFO/роста.

Имя — --name либо basename, UTF-8, непустое, не . или .., максимум 255 символов, без обоих separators/control characters. Использовать одинаковый NAME и fileContent[0]; unique name запрашивается всегда, итоговое NAME берётся из результата сервера.

### 3. Preview и API policy

CommandCatalog::ROUTES включает допустимую пару disk.folder.uploadFile/1; routes(request) выбирает её только для local mode. ApiPolicyGuard проверяет полный mixed plan до credentials/API; strict-rest3 возвращает existing policy-denied/exit4, в том числе при dry-run. --file-id остаётся REST3-only.

PreparedOperation содержит безопасные descriptors: taskId, folder id (API `id`), NAME, sizeBytes, generateUniqueName, два метода/API versions и явную ссылку attach на Disk ID из upload. Контейнеры preview/result служебные; реальные API fields всегда id/data.NAME/fileContent/taskId/fileIds. Никаких payload bytes/Base64/SDK objects/download URL в plan или выводе. Dry-run не создаёт API client и не читает credentials, не обещает права/квоту/успех.

### 4. Ledger и восстановление

Успех: два mutation rows с action upload/attach, taskId, resourceId=Disk ID, confirmed=true, changedFields; local mode дополнительно сохраняет фактическое NAME/sizeBytes без URL. meta ledger описывает оба шага; schemaVersion=1 и mutation TSV columns сохраняются. Изменения shared presenter/ledger допустимы только если нужны для возврата динамического Disk ID, без изменения других команд.

Upload server denial: attach skipped, exit1 без подтверждённого upload. После отправки upload потеря ответа либо malformed success без валидного Disk ID: upload unknown/outcomeUnknown, attach skipped, без retry. Attach может отказать после успешного upload; это exit3 с upload row/ID, complete=false, attach failed. При потере attach ответа — attach unknown/outcomeUnknown и проверка привязки перед ручным повтором. Подтверждённый Disk ID пригоден для `task:file:attach TASK_ID --file-id ID` без второй загрузки. Никакого implicit rollback/delete: orphan остаётся видимым результатом.

Перед каждым request и между шагами проверять RuntimeState cancellation. Ctrl-C после upload возвращает 130 с подтверждённым Disk ID и skipped/unknown attach по факту отправки. Не терять confirmed upload в exception path. ACK attach подтверждает метод, не независимое членство файла в задаче.

### 5. Проверки и источники

Offline tests проходят через production bootstrap с fake ApiTransport: modes/IDs/path/name/limit, exact payload, Disk ID vs FILE_ID, dynamic policy, no-credentials preview, binary preservation, upload deny/malformed/timeout, attach deny/unknown, cancellation и secret/content non-disclosure во всех форматах. Existing --file-id contract и 34-command registration остаются в regression suite.

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

- Base64 payload/memory overhead → SDK chunk encoding + streaming JSON for large files, Content-Length по размеру, fixed-size buffers; limit определяется config перед проверкой файла.
- Task attachment denial после успешной загрузки → durable Disk ID, partial ledger, ручная привязка существующего файла.
- Потерянный upload response → outcomeUnknown без автоматического повторения; проверить целевую папку в портале.
- Не хватает disk scope/квоты/ACL → безопасная серверная ошибка, без смены профиля и расширения rights.
- Подмена/рост local file → regular-file checks и bounded read handle до mutation, без блокирующих pipe reads.
- Shared runtime затрагивает другие команды → полный unit/lint regression и сохранение existing ID mode.

## Delivery

После отдельного запроса apply выполнить задачи и verify, свежий fetch/merge origin/dev и PR в dev. Planning PR не закрывает issue. После подтверждённого implementation merge/закрытия issue — синхронизация/архив OpenSpec, доставка архива через dev PR и cleanup worktree по maintainer. Это не release/publication proof.

## Live fixture documentation

13.10.2026 обязательным MCP проверены [disk.folder.addSubFolder](https://apidocs.bitrix24.ru/api-reference/disk/folder/disk-folder-add-subfolder.html) и [disk.folder.deleteTree](https://apidocs.bitrix24.ru/api-reference/disk/folder/disk-folder-delete-tree.html). Эти REST1 маршруты используются только SDK fixture setup/cleanup, не добавлены в пользовательский CommandCatalog. Cleanup удаляет только известную созданную дочернюю папку, никогда root storage или ранее существовавшую папку.

## Revision: 500 MB and config precedence — 13.10.2026

User requested a 500 MB default and config precedence. Use decimal MB: 500000000 bytes. Runtime --config accepts maxUploadBytes as a strict positive JSON integer; it overrides default in both directions. This is runtime metadata, not a Bitrix24 field or default.task. Invalid values fail before credentials/API. The effective value is passed through InvocationContext into local-file validation and stored in the immutable source descriptor; preview records maxUploadBytes.

The verified CLI PHP memory_limit is 128M. A full 500 MB Base64 string plus JSON would exceed it. Files up to the existing small-file threshold (10 MiB) use SDK encodeString directly. Larger files are encoded with the same SDK encoder in 60000-byte chunks (aligned to Base64 groups); a StreamingBase64 descriptor is carried through the existing SDK Core transport. A Symfony HttpClient decorator converts only that descriptor in fileContent[1] into a streaming JSON body with exact Content-Length. All other requests pass through unchanged; method/version, metadata, redirects, timeout, accounting, and server response handling remain SDK/CLI controlled. No multipart uploadUrl route or new API exception is needed.

Raw native file-handle reads remain isolated in LocalFileSource, with original/effective limit, identity/size checks and RuntimeState cancellation before chunks. SDK MIME CRLF is removed from each chunk to form a valid continuous Base64 JSON string; ordinary Base64 padding occurs only at EOF. Partial source growth/change or Ctrl-C during upload aborts the body; a sent upload without confirmation retains outcomeUnknown and never attaches/retries. Metadata-only dry-run does not encode/read large content.

Verification must drain an actual 500000000-byte file through the production SDK/HttpClient path at memory_limit=128M, asserting exact byte hash and wire Content-Length without buffering the request. Boundary metadata tests use sparse files for configured max/max+1, including config >500 MB. This is local transport proof, not a claim that a portal quota or upload timeout permits 500 MB.
