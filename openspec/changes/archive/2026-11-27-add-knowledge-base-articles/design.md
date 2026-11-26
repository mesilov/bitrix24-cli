# Design

## Context

Мотивация — в proposal.md (Why), поведение — в `specs/knowledge-base/spec.md`.

В CLI уже есть всё, на что опирается новый namespace:

- `ApiTransport::call($method, 3, ...)` вызывает REST 3.0 через SDK. Ошибка API приходит как `Failure` с `details.apiErrorCode`; ошибки валидации DTO REST 3.0 SDK превращает в `ValidationException`, транспорт — в `api-validation-error` с `validation[]`.
- `CommandCatalog::ROUTES` и `ScopeManifest` проверяют scope до вызова (#149); `ApiPolicyGuard` пропускает под `strict-rest3` только маршруты версии 3.
- `InputSourceReader::read(PATH|-, maximum)` читает файл или stdin с ограничением размера (`--description-file`, `--text-file`).
- Стандартный профиль `plan` для `--dry-run`, неполные списки — `complete=false` + `reason` + exit 3, защита от дубля — по образцу `chats:add`.

Фактические ответы REST 3.0, снятые на тестовом портале 26.11.2026 (временная база знаний создана и перенесена в корзину):

| Ситуация | Ответ |
|---|---|
| нет статьи, базы или родителя по ID (`get`, `update`, `add`, `tree.list`, `collection.get`) | HTTP 400, `BITRIX_REST_V3_EXCEPTION_ENTITYNOTFOUNDEXCEPTION` |
| пустой, отсутствующий или длиннее 255 знаков `title` | HTTP 400, `BITRIX_REST_V3_EXCEPTION_VALIDATION_DTOVALIDATIONEXCEPTION`, `validation[].field = title` |
| `parentId` из другой базы | HTTP 400, `NOTE_INVALID_PARENT` |
| `update` без `title`/`markdown` (в том числе только с `parentId`) | HTTP 400, `NOTE_EMPTY_UPDATE` |
| `markdown` больше 1 048 576 байт | HTTP 400, `NOTE_MARKDOWN_TOO_LARGE` |
| поисковый запрос короче 3 символов | HTTP 400, `NOTE_SEARCH_QUERY_TOO_SHORT` |
| две записи `update` подряд от разных пользователей | обе успешны, сохраняется последняя |

Расхождения с документацией: `policyLevel` приходит как `portal`/`private` (в описании `none`/`view`/`manage`); в ответах есть незадокументированные `accessLevel`, `state`, `markdownDescription`; `snippet` размечает совпадения тегом `<mark>`, а не `**`. Поиск находит только что созданную статью сразу. Markdown простого документа (заголовок, жирный, список) возвращается без изменений.

## Goals / Non-Goals

**Goals:** тонкий слой над шестью методами `note.*` без собственной модели статьи; точные имена полей REST 3.0 во вводе и выводе; безопасная запись для агента (dry-run, защита от дубля, один вызов без повтора, сверка ответа); понятные ошибки по фактическим кодам портала.

**Non-Goals:** частичная правка Markdown (diff, patch, append) — API заменяет содержимое целиком; кеширование баз и деревьев; перевод Markdown в другие форматы; обработка вложений `[[image fileId=…]]` — они передаются как обычный текст.

## Decisions

### Namespace и имена команд

`kb:list`, `kb:article:list`, `kb:article:search`, `kb:article:show`, `kb:article:add`, `kb:article:update`. Короткий `kb:` по образцу `task:`/`users:`/`chats:`; «article» вместо API-термина «document», потому что в интерфейсе Битрикс24 это статьи, а `document` в CLI легко спутать с документами CRM и Диска. Имена полей API (`collectionId`, `documentId`) при этом не переименовываются.

Альтернативы: `note:*` (как в API) — непрозрачно для пользователя и конфликтует со смыслом «заметка»; `knowledge:*` — длиннее без выигрыша.

### Ввод

- `--collection ID`, `--parent ID`, позиционный `ID` — положительные целые, иначе usage-error до API.
- `--title` обрезается по краям; 1…255 символов (Unicode) после обрезки — та же граница, что у DTO-валидации портала.
- `--markdown-file PATH|-` читается через `InputSourceReader` с лимитом 1 048 576 байт, ровно как `NOTE_MARKDOWN_TOO_LARGE`. Содержимое передаётся без изменений (без обрезки и нормализации переводов строк). Пустой файл допустим и очищает содержимое статьи при `update`.
- `kb:article:search --query` обрезается; 3…200 символов; `--limit` 1…200, по умолчанию 50.
- `kb:article:update` требует хотя бы один из `--title`, `--markdown-file`; `overwrite: true` передаётся только с `--overwrite`, иначе поле не отправляется (по умолчанию API `false`).

Локальная проверка дублирует ограничения портала намеренно: агент получает usage-error/exit 2 без сетевого вызова и без траты лимита.

### Чтение

- `kb:list`: `note.collection.list` с `pagination.limit = 200`, следующая страница — `pagination.afterCursor = result.nextCursor`. Обход до `nextCursor = null`. Повтор курсора или уже полученного `id` → `pagination-no-progress`, exit 3. Бюджет страниц не вводится: баз знаний на портале единицы или десятки.
- `kb:article:list`: один вызов `note.document.tree.list`; JSON сохраняет вложенность `items[].children[]`, human/plain выводят плоский список с `depth`. `truncated: true` → `complete=false`, `reason: tree-truncated`, exit 3.
- `kb:article:search`: один вызов `note.document.search.list`; у метода нет курсора, поэтому `hasMore` выводится как `meta.hasMore` с подсказкой. `hasMore` не делает результат ошибкой (exit 0): это свойство ранжированной выдачи, а не потеря данных. `snippet` в JSON передаётся как есть (HTML с `<mark>`); human-вывод снимает теги.
- `kb:article:show`: один вызов `note.document.get`; `--markdown` пишет в stdout только `item.markdown` без добавленного перевода строки и несовместим с `--json`/`--plain` (usage-error). Портал отдаёт и статьи из корзины (`isTrashed: true`, проверено 26.11.2026) — CLI их не скрывает.

Все поля ответа передаются в JSON без переименования, включая незадокументированные (`state`, `accessLevel`, `markdownDescription`): CLI их не интерпретирует и не обещает.

### Создание

1. Если не передан `--allow-duplicate-title`: `note.document.tree.list` выбранной базы. Совпадение `title` (после обрезки, с учётом регистра) у статьи с тем же `parentId` → `article-exists`, exit 4, `details.candidates[]` с ID. `truncated` → `search-incomplete`, exit 4. Если `--parent` не найден в дереве, CLI не решает за API и передаёт запрос дальше: портал вернёт `NOTE_INVALID_PARENT` или `ENTITYNOTFOUND`.
2. `note.document.add` один раз с `fields`: `collectionId`, `title`, `parentId` (если задан), `markdown` (если задан).
3. Сверка с `result.item`: положительный `id`, совпадение `title`, `collectionId`, `parentId`. Несовпадение → exit 3 с `readback.mismatch[]`. Отличие `markdown` → `readback.markdownNormalized: true`, запись подтверждена.

Защита от дубля стоит одного чтения и защищает от главного риска агента — повторного запуска после таймаута. Альтернатива без проверки отклонена: одноимённые соседние статьи в базе знаний почти всегда ошибка.

### Обновление

Один вызов `note.document.update` с `id`, `fields` (`title` и/или `markdown`) и `overwrite`, если задан. Сверка — как при создании (`title`, `markdown`). Чтение перед записью не выполняется: оптимистичной блокировки в API нет (поля версии или `updatedAt` в запросе), поэтому предварительное чтение не защищает от гонки и только удваивает вызовы.

Конфликт совместного редактора через REST воспроизвести не удалось, его код неизвестен. До live-приёмки адаптер обрабатывает его так: ошибка `update` с `markdown` без `--overwrite`, код которой не входит в таблицу известных кодов выше, получает в `error.hint` подсказку про `--overwrite` и `details.apiErrorCode`. После снятия кода на портале он становится явной ошибкой `article-conflict` (exit 4).

### Запись без повтора

`add` и `update` вызываются ровно один раз. Потеря ответа транспорта или ответ без `item.id` → `Failure` с `outcomeUnknown = true`; подсказка — `kb:article:list --collection ID` (для `add`) или `kb:article:show ID` (для `update`).

### Ошибки

Адаптер переводит `apiErrorCode` в коды CLI:

| API | CLI |
|---|---|
| `…ENTITYNOTFOUNDEXCEPTION` в `get`/`update` | `article-not-found`, exit 1 |
| `…ENTITYNOTFOUNDEXCEPTION` в `tree.list` | `collection-not-found`, exit 1 |
| `…ENTITYNOTFOUNDEXCEPTION` в `add` | `parent-not-found`, если база уже подтверждена чтением дерева и передан `--parent`; иначе `collection-not-found`; exit 1 |
| `NOTE_INVALID_PARENT` | `api-validation-error`, `validation[]` с `field: parentId`, `option: --parent` |
| DTO-валидация | существующий `api-validation-error`; `title` → `option: --title`, `markdown` → `option: --markdown-file` |
| `NOTE_MARKDOWN_TOO_LARGE`, `NOTE_SEARCH_QUERY_TOO_SHORT`, `NOTE_EMPTY_UPDATE` | `api-validation-error` (на практике отсекаются локальной проверкой) |
| `ACCESS_DENIED` (в том числе «REST API is available only on commercial plans») | существующий `permission-denied` с подсказкой про тариф и scope `note` |

### Маршруты и scope

`CommandCatalog::ROUTES`: `kb:list` → `note.collection.list`; `kb:article:list` → `note.document.tree.list`; `kb:article:search` → `note.document.search.list`; `kb:article:show` → `note.document.get`; `kb:article:add` → `note.document.tree.list` (если нет `--allow-duplicate-title`), `note.document.add`; `kb:article:update` → `note.document.update`. Все версии 3. `ScopeManifest` получает отдельную таблицу методов REST 3.0 вне task-группы (прежняя таблица «прочих» методов описывает только REST 1.0): каждый `note.*` → `[['note']]`; таблица входит в ревизию манифеста.

### Вывод

Профили `kb-collections` (TSV: `id`, `name`, `policyLevel`, `accessLevel`, `isArchived`), `kb-tree` (`id`, `parentId`, `depth`, `title`), `kb-search` (`documentId`, `collectionId`, `title`, `score`, `sharedAccess`), `kb-article` (`id`, `collectionId`, `parentId`, `title`, `updatedAt`; human-вывод добавляет Markdown целиком), `kb-article-written` (`id`, `collectionId`, `parentId`, `title`, `confirmed`, `readback`, `markdownNormalized`). Dry-run — стандартный профиль `plan`; поле `markdown` в плане заменяется на `markdownBytes` и `markdownSha256`, чтобы план не печатал мегабайт текста.

### Код

- `Application/KnowledgeBase/Port/KnowledgeBaseGateway` + `Infrastructure/Bitrix24/Rest3KnowledgeBaseAdapter` — шесть вызовов и перевод ошибок.
- `Application/KnowledgeBase/Request/*` — нормализованный ввод; `KnowledgeBaseInputMapper` в `Console`.
- `Application/KnowledgeBase/KnowledgeBaseReadService` (список, дерево, поиск, чтение) и `ArticleWriteService` (дубль, план, запись, сверка).
- `Console/Command/KnowledgeBase/*Command` — шесть команд.

## Risks / Trade-offs

- [Код конфликта совместного редактора неизвестен] → общая подсказка про `--overwrite` для неизвестных ошибок `update`; задача live-приёмки снять код через UI-редактор и превратить в `article-conflict`.
- [`update` заменяет текст целиком и не защищён от гонки] → сценарий «прочитать → изменить → записать» в документации и скилле; `updatedAt` в выводе `show` позволяет агенту заметить чужую правку перед записью.
- [Сервер может нормализовать сложный Markdown (таблицы, вложения)] → расхождение текста — признак `markdownNormalized`, а не ошибка; сохранённый текст возвращается в выводе.
- [Защита от дубля видит только дерево без архивных статей] → архивная одноимённая статья дублем не считается; это граница проверки, указанная в документации.
- [Незадокументированные значения `policyLevel` и поля ответа] → CLI передаёт их как есть и не валидирует перечисление.
- [Live integration-тест пишет на тестовый портал] → тест создаёт временную базу знаний с уникальным названием и переносит её в корзину в `tearDown` через `note.collection.delete` (вызов SDK, не CLI-команда).

## Migration Plan

Новые команды; существующие команды и форматы не меняются. Откат — удаление команд, маршрутов и scope `note` из манифеста.

## Open Questions

- Код ошибки конфликта совместного редактора при `note.document.update` — снять на live-приёмке; меняет только перевод ошибки в адаптере, не спецификацию.
