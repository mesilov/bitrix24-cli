# TAGS contract — 13.10.2026

## Sources and version boundary

Из worktree подтверждён effective config `codex mcp get bitrix24_dev --json`; официальный MCP ответил на bitrix-search → details. Источники:

- [REST3 task fields](https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html): `tags` нельзя передать в fields add/update. Чтение — массив объектов с integer `id` и string `name`, select `tags.id`/`tags.name`.
- [REST1 tasks.task.update](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-update.html): `taskId` integer, `fields` object, result.task.id. MCP method-details смешивает обе версии; v3 `id` не переносится в v1 payload.
- [tasks.task.getFields](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-fields.html): стандартные/пользовательские metadata. Реальный ответ выбранного портала не содержит TAGS, поэтому metadata не доказывают тип, writable и ограничения тегов.

## Verified on the configured portal

Probe использует production EnvConnectionResolver → B24ClientProvider → SdkApiTransport и Rest3TaskAdapter, выбранный исходный env смонтирован read-only. Credentials и hostname в receipt не сохраняются.

[Receipt](portal-contract-receipt.json): actingUserId=1, временная задача=5870, создание REST3, три REST1 записи, три независимых REST3 чтения, удаление REST3. Все write ACK вернули task.id=5870; cleanupConfirmed=true.

Подтверждённый writable payload:

```json
{"taskId":5870,"fields":{"TAGS":["#плановая","Этап 1"]}}
```

- Точное имя — `TAGS`, API version 1.0; `fields` содержит только этот ключ.
- Тип, проверенный выполнением — список строк: `['#плановая', 'Этап 1']`; #, Unicode и внутренний пробел сохранены.
- Замена на `['#ошибка']` оставила ровно это название и удалила предыдущие два.
- Очистка `TAGS:[]` сохранилась как `tags:[]` при независимом чтении.
- Без TAGS задача создаётся через REST3: поле необязательно для этого сценария. Отсутствие CLI опции не планирует REST1 вызов.
- Ответ write — result.task.id той же задачи. В SDK result содержится массив task/id; boolean/null ACK не используется.

Это фактический контракт одного выбранного подключения, не универсальная metadata-гарантия. Scopes: документация различает REST1 task и REST3 tasks; собственная роль userId=1 успешно выполнила запросы, denied-role не проверялась. Максимальная длина/количество тегов, нормализация крайних пробелов и иные роли не установлены; CLI не придумывает лимиты и не переименовывает названия. На других порталах серверный отказ возвращается явно, без fallback/retry.
