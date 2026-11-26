# Proposal

## Why

В компаниях на Битрикс24 регламенты, инструкции и договорённости живут в Базе знаний 2.0, но агент через CLI не может ни прочитать их, ни записать итог работы в статью: команд для Базы знаний нет. В REST 3.0 появился раздел `note.*` (scope `note`), который закрывает чтение и запись статей в Markdown. Минимальной поддержки достаточно, чтобы агент находил нужную статью, читал её, создавал новую и обновлял существующую.

## What Changes

- Добавить JTBD **KB-01…KB-05** в новую capability `knowledge-base` и в `docs/task-jtbd.md`: список баз знаний, поиск статьи, чтение, создание, обновление.
- Добавить namespace `kb:*` из шести команд поверх REST 3.0:
  - `kb:list` — доступные базы знаний (`note.collection.list`, полный обход курсором `nextCursor`);
  - `kb:article:list --collection ID` — дерево статей одной базы (`note.document.tree.list`), обрезанное дерево сообщается как неполное;
  - `kb:article:search --query TEXT` — поиск по заголовку и тексту во всех доступных базах (`note.document.search.list`);
  - `kb:article:show ID [--markdown]` — заголовок, Markdown и служебные поля статьи (`note.document.get`); `--markdown` выводит только содержимое для правки в файле;
  - `kb:article:add --collection ID --title TITLE [--parent ID] [--markdown-file PATH|-] [--allow-duplicate-title] [--dry-run]` — создание статьи (`note.document.add`) с защитой от дубля заголовка среди соседних страниц;
  - `kb:article:update ID [--title TITLE] [--markdown-file PATH|-] [--overwrite] [--dry-run]` — замена заголовка и/или всего содержимого (`note.document.update`).
- `note.document.update` заменяет `markdown` целиком, частичной правки нет. Спецификация и документация закрепляют сценарий агента: `kb:article:show --markdown` → правка локально → `kb:article:update --markdown-file`.
- Ошибки API превращаются в понятные коды CLI по фактическим ответам портала; конфликт с несохранёнными правками совместного редактора подсказывает `--overwrite`.
- Объявить маршруты `note.*` в `CommandCatalog` и scope `note` в `ScopeManifest`; все маршруты REST 3.0, поэтому `strict-rest3` их разрешает.

Вне объёма: архивирование и удаление статей, создание, изменение, архивирование и удаление баз знаний, файлы и вложения (`note.file.*`), схемы полей (`*.field.*`), старая база знаний на лендингах (`landing.*`, REST 2.0).

## Capabilities

### New Capabilities

- `knowledge-base`: чтение и запись статей Базы знаний 2.0 через namespace `kb:*` — сценарии KB-01…KB-05, поиск статьи, исход записи, ошибки и политика API.

### Modified Capabilities

Нет. Новые маршруты попадают под существующие требования `connection-scope-validation` (манифест для каждой поддержанной пары method/version) без изменения самих требований.

## Impact

Новые классы: команды `kb:*` в `Console/Command/KnowledgeBase`, запросы и сервисы в `Application/KnowledgeBase`, порт и адаптер REST 3.0 `Rest3KnowledgeBaseAdapter`, маппер ввода. Изменяются `CommandCatalog` (команды и маршруты), `ScopeManifest` (`note.*` → `note`), `ResultPresenter` (профили вывода), `config/services.php`. Документация: новый `docs/knowledge-base.md`, `docs/cli-command-map.md`, `docs/auth-scopes.md`, `docs/task-jtbd.md`, README, CHANGELOG. Тесты: unit-контракты на fixtures и live integration-тест на тестовом портале с временной базой знаний, которая удаляется в корзину после теста.

Сценарий для агента описывается также в скилле `bitrix24-cli` (сейчас называется `bitrix24-tasks` и переименовывается, потому что покрывает уже не только задачи), который живёт в отдельном репозитории [mesilov/bitrix24-cli-skill](https://github.com/mesilov/bitrix24-cli-skill); переименование и правка скилла — отдельный MR туда после поставки команд.

Официальные источники (MCP документации Битрикс24, `bitrix-search` → `bitrix-method-details`, 26.11.2026):
[обзор документов](https://apidocs.bitrix24.ru/api-reference/note/document/index.html),
[`note.collection.list`](https://apidocs.bitrix24.ru/api-reference/rest-v3/note/collection/note-collection-list.html),
[`note.document.tree.list`](https://apidocs.bitrix24.ru/api-reference/rest-v3/note/document/note-document-tree-list.html),
[`note.document.search.list`](https://apidocs.bitrix24.ru/api-reference/note/document/note-document-search-list.html),
[`note.document.get`](https://apidocs.bitrix24.ru/api-reference/rest-v3/note/document/note-document-get.html),
[`note.document.add`](https://apidocs.bitrix24.ru/api-reference/note/document/note-document-add.html),
[`note.document.update`](https://apidocs.bitrix24.ru/api-reference/note/document/note-document-update.html).
Отдельный обзор раздела `note.collection.*` в MCP по названию не открылся (возвращается обзор документов), поэтому методы баз знаний сверены по их карточкам. Фактические ответы и коды ошибок сняты на тестовом портале 26.11.2026 и перечислены в design.

Снимок планирования: `dev` `68b9cb3`.

## GitHub issues

- [#234 — Базы знаний 2.0: список баз, поиск, чтение, создание и обновление статей](https://github.com/mesilov/bitrix24-cli/issues/234). Change покрывает весь объём issue: KB-01…KB-05, шесть команд `kb:*`, scope `note`, документацию; правка скилла — отдельным MR в репозиторий скилла.
- Milestone: `0.3.0`; type: Feature; label: `enhancement`.
