# Proposal

## Why

В CLI 0.1.0 нельзя создать или изменить задачу с обязательными для пользователя тегами `#плановая` / `#ошибка`. Требования по тегам выделены из #58 в #71 для milestone 0.2.0; пользовательские поля и общий сбой advanced-метаданных остаются отдельной работой.

## What Changes

- Добавить повторяемую опцию `--tag NAME` в `task:add` и `task:update`, а также `--clear-tags` в `task:update`.
- Записывать полный явно указанный набор тегов через заранее выбранный REST 1.0 маршрут с полем `TAGS`; обычные поля задачи продолжать отправлять в REST 3.0 с точными API-именами.
- Обеспечить tags-only update, сохранение тегов при отсутствии опций, явные API-маршруты в dry-run, ранний отказ `strict-rest3` и корректную отчётность частичной записи.
- Проверять сохранённые теги через существующий `task:show --select tags.id --select tags.name`.
- Пользовательские поля, исправление общей проверки `editable`, advanced-запись `tags`/`TAGS`, profile defaults тегов и глобальный CRUD тегов не входят в change.

## Capabilities

### New Capabilities

Нет: поведение относится к существующему runtime задач.

### Modified Capabilities

- `task-console-runtime`: явные CLI-опции тегов, двухшаговые планы создания/изменения, версии API и partial/outcomeUnknown.
- `task-mvp-scope`: узкий допуск REST 1.0 `tasks.task.update` только для `TAGS` в сценариях add/update.

## Impact

Изменяются AddTaskCommand/UpdateTaskCommand, TaskInputMapper, request DTO, AddTaskHandler/UpdateTaskHandler, CommandCatalog::ROUTES и DI. Нужен отдельный gateway/adapter записи тегов, использующий существующий ApiTransport; новые зависимости и команды не нужны. Проверки затрагивают unit contracts/behavior/legacy codec и собственные integration cases AddTaskCommandTest/UpdateTaskCommandTest. Существующий FieldSchema::WRITABLE не расширяется read-only полем tags.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/71 — весь реализуемый scope тегов; milestone 0.2.0, enhancement.
- https://github.com/mesilov/bitrix24-cli/issues/58 — источник выделенных требований. Оставшиеся userFields и editable=false не реализуются этим change.

## Evidence boundary

План основан на коде dev `6d9fe39` и официальном MCP документации, проверенном 13.10.2026. REST 3.0 запрещает передачу tags в add/update. Сохранение TAGS в REST 1.0 add сообщено автором #58; новая проверка портала не выполнялась. Замена/очистка через REST 1.0 update и права должны получить отдельный portal receipt при реализации. Подготовленный план не является runtime proof.
