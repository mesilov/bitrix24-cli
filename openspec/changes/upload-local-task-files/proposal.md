# Proposal

## Why

`task:file:attach` принимает только существующие ID файлов Диска. Пользователь не может приложить локальный документ к задаче через публичный CLI; issue #62 запрашивает этот сценарий для milestone 0.2.0.

## What Changes

- Расширить существующую `task:file:attach TASK_ID` отдельным режимом `--file PATH --folder-id ID`: загрузить один локальный файл в явно выбранную папку Диска и прикрепить полученный Disk ID к задаче.
- Разделить режимы: повторяемый `--file-id` для существующих файлов либо один `--file` с обязательным `--folder-id`. Для локального режима предложить optional `--name NAME`, иначе basename пути; при совпадении имени запросить `generateUniqueName=true`, без перезаписи.
- Ввести ограничение прямой Base64 загрузки 10 MiB на файл как лимит CLI, локальную проверку пути и безопасный dry-run без содержимого файла.
- Явно допустить REST1 `disk.folder.uploadFile` только для локального режима в task-v3 policy. Сохранить REST3 attach, предварительное отклонение mixed plan в strict-rest3 и существующий формат data/meta/error.
- Учесть два неатомарных шага, сохранение Disk ID после успешной загрузки, partial/outcomeUnknown/Ctrl-C и восстановление привязки через `--file-id` без повторной загрузки.
- Использовать Symfony Filesystem/Path для локальной работы с файлами. До реализации оценить файловые helpers, Disk upload services/results и тестовые подходы зафиксированного b24phpsdk; переиспользовать совместимые наработки, причины собственного кода зафиксировать в design.
- Добавить контрактные тесты, live case существующего AttachTaskFilesCommandTest и пользовательские примеры.

## Capabilities

### New Capabilities

- `task-file-upload`: локальный файл → явно выбранная папка Диска → привязка к задаче, ограничения ввода и восстановление после частичного выполнения.

### Modified Capabilities

- `task-console-runtime`: допуск полного mixed route plan для локального режима существующей команды.
- `task-mvp-scope`: явный допуск Disk upload как расширения 0.2.0 без расширения исходных 30 команд и остальных исключённых операций.
- `cli-experience`: уточнение файлового ввода: бинарный `--file` принимает локальный PATH, без stdin `-`, в отличие от текстовых/JSON файловых флагов.

## Impact

Затронуты AttachTaskFilesCommand, TaskInputMapper, request/handler, application port и SDK adapter, CommandCatalog::ROUTES, preview/ledger/presentation, unit/integration tests и command catalog/README. Используются Symfony Filesystem и совместимые helpers/services b24phpsdk через существующие runtime boundaries; symfony/filesystem уже есть в lockfile транзитивно, при прямом использовании объявить прямую зависимость. Другие новые dependencies и обход через внешний curl/MCP для портальных операций не нужны. Общая Disk namespace, выбор/создание папок, автоматический личный диск, multipart/большие файлы, streaming/stdin, произвольные ACL и массовая загрузка вне scope.

## GitHub issues

- [#62](https://github.com/mesilov/bitrix24-cli/issues/62) — загрузка локального файла на Диск и последующая привязка к задаче; milestone 0.2.0.
- #12 упоминается как контекст: его O8/local upload был исключён из `improve-task-cli-dx`; реализация этого сценария принадлежит только настоящему change.

## Evidence boundary

13.10.2026 из checkout выполнены `codex mcp get bitrix24_dev --json` и реальные `bitrix-search` → `bitrix-method-details`. Подтверждены документированные контракты [Disk upload](https://apidocs.bitrix24.ru/api-reference/disk/folder/disk-folder-upload-file.html) и [REST3 attach](https://apidocs.bitrix24.ru/api-reference/tasks/rest-v3/tasks-task-file-attach.html). Это planning evidence; runtime, CI, live права и membership задачи пока не проверены. Исходная база — dev `6d9fe39b4f6d431c70680adefdab970cb0ad2db4`.
