# Design

## Context

См. proposal.md — Why и delta specs. На dev `6d9fe39` AddTaskCommand/UpdateTaskCommand предлагают basic flags и отдельный advanced JSON. TaskInputMapper::patch вызывает FieldSchema::localPatch и помещает только разрешённые REST3 поля в request.fields; advanced дополнительно проверяет editable через FieldSchema::validateWritable. Tags/userFields отсутствуют в WRITABLE, и это соответствует документированному запрету REST3.

AddTaskHandler/UpdateTaskHandler сейчас выполняют по одной REST3 записи. Plan::step уже умеет версию и номер шага, но не описывает зависимость будущего taskId. ApiPolicyGuard сверяет Route с CommandCatalog::ROUTES и блокирует non-v3 при strict-rest3 до подключения. Rest1ParticipantAdapter уже использует REST1 tasks.task.update и подтверждает совпадающий result.task.id. ExecutionLedger поддерживает confirmed/failed/unknown/skipped и partial exit3; это основа учёта составной записи, а не доказательство её готовности для тегов.

## Goals / Non-Goals

**Goals:** использовать существующие mapper → request → handler → gateway → ApiTransport слои; сохранять точные поля каждой версии API и обеспечить наблюдаемое выполнение составного плана.

**Non-Goals:** расширять REST3 writable allowlist, превращать advanced JSON в произвольную legacy запись, переключать все add/update на REST1, добавлять справочник тегов или profile default.task.tags.

## Decisions

### D1. Явные опции и отдельное значение request.TAGS

Повторяемый `--tag NAME` задаёт полный набор; `--clear-tags` в update задаёт `[]`; отсутствие обоих означает `null` и отсутствие тегового шага. DTO хранит `?array $TAGS` отдельно от REST3 `$fields`; uppercase соответствует выбранному REST1 полю, собственный alias `tags` для writable DTO/JSON не вводится. Точное повторение имени можно дедуплицировать с сохранением первого порядка; пробелы, Unicode, # и регистр сохраняются. Пустые и trim-пустые значения отклоняются. Лимиты длины не выдумываются: подтверждаются в проверке контракта, остальные серверные отказы передаются как ошибки.

Tag flags не смешиваются с fields/fields-file, как и существующие basic field flags. Advanced `tags`/`TAGS` остаются отклонёнными с указанием `--tag`. Tags-only update обходит запрет пустого ordinary patch только когда явно задан корректный TAGS набор. Create по-прежнему требует title/creatorId/responsibleId, включая существующие profile defaults.

Альтернативы: JSON-массив в `--tags` требует экранирования и парсинга; запись tags в advanced противоречит read-only REST3. Повторяемый --tag соответствует существующим repeatable CLI options и простым shell-сценариям.

### D2. Заранее выбранная композиция REST3 и REST1

Предлагается отдельный TaskTagGateway и Rest1TaskTagAdapter: `set(int $taskId, array $TAGS)` отправляет `tasks.task.update`, version 1, `{'taskId': id, 'fields': {'TAGS': names}}`. Gateway не принимает обычные legacy поля. Используется существующий ApiTransport, без curl или отдельного SDK/клиента. Method-specific ACK проверяет result.task.id той же задачи по аналогии с participants; false, null, чужой ID или повреждённый ответ не подтверждают успех.

Create: REST3 add ordinary fields → получить ID → REST1 TAGS update. Update: REST3 ordinary update, если patch непустой → REST1 TAGS update. Tags-only: только REST1 update. Отсутствие тегов: текущий REST3 путь. Подготовка не требует чтения tag metadata REST3 с editable=false. Каталог допускает REST1 update у add/update; handler объявляет его только при explicit TAGS. Strict-rest3 блокирует весь составной план до credentials/API, включая первый add.

Альтернативы: перевод всего add на REST1 потребует маппинга всех REST3 полей и разрушит заявленную policy; implicit fallback после REST3 ошибки нарушит детерминированность и может повторить запись. Композиция ограничивает legacy payload одним API-полем, но имеет partial failure window.

### D3. Проверка TAGS — подтверждённый gate до кода

Официальный MCP подтвердил read-only `tags` в REST3 и объект `{id,name}` для чтения. Доступные ответы MCP о REST1 update смешивают v1/v3 параметры; пример tasks.task.getFields не содержит TAGS. Поэтому тип/записываемость TAGS и пустой массив не объявляются подтверждёнными документацией. `TAGS:["#plan"]` в REST1 add подтверждено только сообщением автора #58.

13.10.2026 gate выполнен реальными REST3 add → REST1 set → replace → clear и REST3 readback на disposable fixture 5870; cleanup подтверждён. Реальный getFields не содержит TAGS. Тип, writable и семантика подтверждены выполнением на выбранном портале, без универсальной metadata-гарантии; см. api-contract.md и portal-contract-receipt.json. Проверить taskId/fields и task.id ACK. Зафиксировать имя, тип, необязательность, замену/очистку, Unicode/# и ограничения в api-contract.md. Если gate не подтверждается, остановить API-зависимую реализацию и скорректировать план через OpenSpec; не подставлять другой ключ/метод или обход автоматически. Сценарии specs описывают требуемое поведение, не свидетельствуют о поддержке конкретного портала.

### D4. Dry-run и результат выполнения

Plan::step для нового taskId расширяется служебной зависимостью `taskIdFromStep: 1`: второй шаг имеет taskId=null до выполнения, fields.TAGS и apiVersion=1.0. Это метаданные CLI, не поле Bitrix24 API. Update показывает известный taskId во всех шагах. В dry-run запрещены оба write-запроса; route policy выполняется раньше preview.

ExecutionLedger фиксирует create/update и tag write отдельно. После известного ID и сбоя второго шага возвращается complete=false, exit3, исходная безопасная причина и ledger; созданную задачу не удалять. При Ctrl-C сохраняется exit130. Если первый create не подтверждён, неизвестный ID не выдумывается и новые запросы не выполняются. Отправленный неподтверждённый теговый шаг сохраняет outcomeUnknown; нормализатор ACK не должен превращать такой ответ в утверждение «ничего не записано». Не выполнять автоматический retry. Восстановление: показать известный TASK_ID, предложить task:show с tags.id/tags.name и повтор только task:update с тегами после сверки.

Не обещать транзакционность mixed update: ordinary fields могут уже сохраниться. Неподтверждённая одиночная tags-only запись возвращает исходную ошибку exit1; partial возможен только при наличии ранее подтверждённого шага. Форматы human/plain/json проходят существующие renderer contracts; подтверждённые данные остаются доступны, без SDK objects/credentials.

## Risks / Trade-offs

- [REST1 TAGS contract не подтверждён полностью] → gate D3 до adapter, отдельный api-contract receipt и portal readback; mocks не закрывают gate.
- [Обязательные теги записываются после создания] → видимый partial с ID, без автоматического удаления/повторного create; атомарность не обещается.
- [Права/scopes REST1 task и REST3 tasks различаются] → серверные permission errors сохраняются, отказ второго шага учитывается; документировать scopes после gate.
- [Сериализация пустого массива SDK] → unit test через реальный SdkApiTransport/MockHttpClient проверяет payload TAGS=[]; живое чтение подтверждает очистку отдельно.
- [Advanced metadata defect #58 сохраняется] → явные basic tag options не требуют editable; тест подтверждает, что общий gate не отключён.
- [Одновременное изменение тегов] → явная замена полного набора, без скрытого read/merge и обещаний atomicity.

## Migration Plan

Изменение предназначено для 0.2.0. Обновить help/README/command catalog и changelog с примерами create/replace/clear, REST1 policy и partial recovery. Схема конфигурации не меняется. Доставить через PR в dev после fresh dev merge, unit/quality/OpenSpec checks и отдельного отчёта о portal gate. Откат — revert feature commit через PR; пользовательские задачи не откатываются автоматически. Change архивировать только после завершения реализации и проверки, по maintainer workflow.

## API sources and evidence

Проверено 13.10.2026: `codex mcp get bitrix24_dev --json` из worktree подтверждает endpoint https://mcp-dev.bitrix24.tech/mcp; реальные bitrix-search → details ответы получены без credentials.

- MCP bitrix-search `tasks.task.add` → bitrix-article-details `Поля задачи в REST 3.0`: https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html — tags запрещён в fields add/update; чтение select tags.id/tags.name, id integer/name string.
- MCP bitrix-search `tasks.task.add tasks.task.update TAGS tags REST 3.0` → bitrix-method-details `tasks.task.update`: https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/tasks-task-update.html — ответ смешивает v1/v3, не источник полного TAGS write-контракта.
- MCP bitrix-search `tasks.task.getFields TAGS` → bitrix-method-details `tasks.task.getFields`: https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-fields.html — стандартные/пользовательские metadata; TAGS в примере не найден.
- Официальная REST1 update страница: https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-update.html — taskId integer, fields object, result.task.id; не доказывает TAGS type/clear.
- Источник пользовательского portal receipt: https://github.com/mesilov/bitrix24-cli/issues/58 — REST1 add с TAGS сохраняет значение. Новые portal вызовы в этой фазе не выполнялись.
