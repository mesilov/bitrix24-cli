# Design

## Context

См. proposal.md. База — dev `2e83e8b634baab3fd2bbeec6ec51c3cdbf20f403`. TaskHandoverService уже собирает полный снимок и использует проверку членства. TaskLifecycleService проверяет action permission, needsControl, requireResult, native result и читает фактический post-state. Необходимо сохранить эти ограничения при композиции.

## Goals / Non-Goals

**Goals:** точная выборка, ограниченный план штатных переходов, отдельное подтверждение назначения и статуса, проверка полного итога.

**Non-Goals:** прямое изменение status в update, автоматическое создание результата, impersonation, сообщения, компенсационный откат, повтор mutation, обработка скрытых подключению задач. `declined` не имеет документированного действия цели.

## Decisions

1. CLI: необязательные `--source-status` (четыре открытых статуса), `--exclude-id ID` (повторяемый, дедупликация), `--target-status` (пять достижимых статусов). Валидация до credentials/API. Фильтр source передаётся существующему scanner через where.status; исключения применяются к полному снимку до планирования записей. Альтернатива «передать всё, затем исправить» не обеспечивает точную выборку.
2. Снимок хранит sourceStatus; перед записью требуется точное совпадение. После update перед переходом проверяются ответственный, проект и сохранённый sourceStatus. Без target итоговый статус равен sourceStatus. Так обнаруживаются изменения между открытыми статусами и серверный сброс при назначении.
3. Использовать TaskLifecycleService для каждого перехода. Новый небольшой планировщик выбирает следующее действие по реальному статусу: deferred → defer; in_progress → start (после disapprove для контроля); pending → pause (из deferred: start, pause; из контроля: disapprove и при необходимости pause); completed → complete либо approve из контроля; supposedly_completed → complete только с needsControl=true. Для completed допускается complete → approve. Никакой переход не повторяется; максимум три различных действия. При неожиданном состоянии, запрете права или недостижимой цели пакет останавливается.
4. Полный повторный GET подтверждает responsibleId/groupId/expectedStatus после всех действий. `status=confirmed` означает совместное совпадение. Отдельные assignmentStatus, transitionStatus, verificationStatus, sourceStatus, targetStatus, actualStatus и transitionActions сохраняют известный результат. Успешное назначение и отказ перехода дают partial/exit3, unknown transition сохраняет подтверждённое назначение и outcomeUnknown=true; ACK и неудачный readback дают unverified. Отмена сохраняет exit130. Все последующие задачи skipped. Plain/human показывают компоненты, JSON сохраняет историю действий.
5. Dry-run остаётся read-only: полный снимок, ID исключений и консервативный условный план с допустимыми lifecycle-действиями и чтениями прав/результата. Промежуточные права проверяются только при достижении соответствующего состояния; preview не обещает их доступность после назначения. План не представляет условные шаги как выполненные.

```mermaid
flowchart LR
  A[Полный снимок и исключения] --> B[Повторная проверка sourceStatus]
  B --> C[Назначение и проверка карточки]
  C --> D{Целевой статус задан?}
  D -- да --> E[Права и штатные переходы]
  D -- нет --> F[Ожидается sourceStatus]
  E --> G[Проверка ответственного, проекта и статуса]
  F --> G
  G --> H[confirmed либо частичный результат]
```

## API evidence

16.10.2026 из доверенного worktree выполнен `codex mcp get bitrix24_dev --json`: enabled=true, endpoint совпадает с `.codex/config.toml` (required=true). Реальные bitrix-search и bitrix-method-details ответили успешно. Проверены первоисточники:

- [REST3 update](https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/tasks-task-update.html): id integer, fields object; responsibleId integer. Существующий adapter отправляет только responsibleId.
- [REST3 get](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-rest-v3.html): id integer, select array, вложенные responsible.id/group.id; status строковый в действующем контракте проекта.
- [REST3 action permissions](https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/tasks-task-access-get.html): id integer, права текущего подключения.
- REST1 [start](https://apidocs.bitrix24.ru/api-reference/tasks/status/tasks-task-start.html), [pause](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-pause.html), [defer](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-defer.html), [renew](https://apidocs.bitrix24.ru/api-reference/tasks/status/tasks-task-renew.html), [complete](https://apidocs.bitrix24.ru/api-reference/tasks/status/tasks-task-complete.html), [approve](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-approve.html), [disapprove](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-disapprove.html): taskId integer; результат подтверждает task.id. В этой операции renew не нужен: исходная выборка содержит только открытые задачи.
- [REST3 native results](https://apidocs.bitrix24.ru/api-reference/tasks/rest-v3/result/tasks-task-result-list.html): фильтр taskId, конечная pagination. Переиспользуется существующий gate завершения; CLI не создаёт результат.

MCP-описания смешивают сведения REST1/REST3 (например, result.list перечисляет также legacy числовые status). Контракт действующих adapters сохраняется и проверяется mock-тестами; live-наблюдения новых переходов не заявляются.

## Risks / Trade-offs

- Назначение может лишить подключение права lifecycle → отдельная ошибка перехода после подтверждённого назначения, без отката.
- Сервер может завершить задачу вместо контроля или иначе обработать disapprove → итог сравнивается с точной целью; недостижимая цель не считается успехом.
- autocompleteSubTasks может влиять на подзадачи → настройки не меняются, возможный серверный эффект документируется в preview; полного охвата побочных объектов нет.
- Между чтением и mutation возможна гонка → повторные проверки сужают окно, но API без compare-and-swap не даёт атомарности; итог относится к моменту последнего чтения.

## Migration Plan

Поставка исходников через MR в dev. Обновление релизных бинарников и live-приёмка выполняются отдельно. Для отката кода используется обычный revert через MR; выполненные назначения и переходы автоматически не откатываются.
