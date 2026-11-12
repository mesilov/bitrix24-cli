## 1. Подготовка

- [x] 1.1 Сверить task.elapseditem.add/update/get/getlist/getmanifest через MCP документации Битрикс24 и проверить сценарии issue сырыми запросами к REST на тестовом портале с cleanup; записать источники и ответы в design.md.
- [x] 1.2 Связать issue #179 и change: раздел `## GitHub issues` в proposal.md и раздел `## OpenSpec` в описании issue.

## 2. Реализация

- [x] 2.1 Добавить `createdDate` в `AddTaskTimeEntryRequest`/`UpdateTaskTimeEntryRequest` и проверку `--created-date` через `FieldSchema::date` в `TaskInputMapper`.
- [x] 2.2 Передавать `CREATED_DATE` в `ARFIELDS` через `TimeEntryGateway`/`Rest1TimeEntryAdapter` только при заданном значении; показывать `createdDate` в плане и `changedFields` `TimeEntryService`.
- [x] 2.3 Добавить флаг `--created-date` и пример в справку `task:time:add`/`task:time:update`.

## 3. Тесты

- [x] 3.1 Unit: контрактные payload add/update с `CREATED_DATE`, неизменный payload без флага, отказ неверных дат без вызовов API, `createdDate` в dry-run и `changedFields`.
- [x] 3.2 Integration: add и update с `--created-date` и чтение `createdDate` из `task:time:list`.

## 4. Документация и проверка

- [x] 4.1 Обновить README, `docs/task-jtbd.md` (EMP-06) и `CHANGELOG.md` со ссылкой на #179; `docs/cli-command-map.md` генерируется из первого примера и сверяется `make cli-reference-check`.
- [x] 4.2 Выполнить `make check`, `make unit`, `make lint-all`, `make cli-reference-check`, `openspec validate --strict` и интеграционные тесты add/update на тестовом портале; записать результаты в verification.md.
