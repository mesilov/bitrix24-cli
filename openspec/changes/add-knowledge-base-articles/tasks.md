# Tasks

## 1. Трассировка и контракт API

- [x] 1.1 Сверить `note.collection.list`, `note.document.tree.list`, `note.document.search.list`, `note.document.get`, `note.document.add`, `note.document.update` через официальный MCP документации Битрикс24 и снять фактические ответы и коды ошибок на тестовом портале; ссылки и границы — в proposal/design.
- [x] 1.2 Создать issue #234 (Feature, `enhancement`, milestone `0.3.0`) с разделом `## OpenSpec`, указывающим change `add-knowledge-base-articles` и его путь; проверить `gh issue view 234`.

## 2. Каркас namespace, маршруты и scope

- [x] 2.1 Порт `KnowledgeBaseGateway` и адаптер `Rest3KnowledgeBaseAdapter` для шести методов `note.*` (REST 3.0) с переводом `apiErrorCode` по таблице design; unit-тесты адаптера на fixtures с реальными ответами портала проверяют payload с точными именами полей и каждый код ошибки.
- [x] 2.2 Объявить шесть команд и маршруты в `CommandCatalog`, `note.*` → `note` в `ScopeManifest`, зарегистрировать сервисы в `config/services.php`; контрактные тесты каталога и полноты манифеста проходят, `list kb` и help работают офлайн, `strict-rest3` пропускает все `kb:*`, отсутствие scope `note` даёт `scope-missing` только для `kb:*`.
- [x] 2.3 Описать scope `note` в `docs/auth-scopes.md`; проверить, что таблица scope покрывает все маршруты `kb:*`.

## 3. Чтение: KB-01, KB-02, KB-03

- [x] 3.1 `kb:list`: обход курсором `nextCursor` по 200, остановка `pagination-no-progress` (exit 3), профиль `kb-collections`; unit-тесты на одну и несколько страниц, пустой список и повтор курсора.
- [x] 3.2 `kb:article:list --collection ID`: дерево с вложенностью в JSON и `depth` в human/plain, `truncated` → `tree-truncated` (exit 3), `collection-not-found`; unit-тесты.
- [x] 3.3 `kb:article:search --query TEXT [--limit N]`: локальная проверка 3…200 символов и лимита 1…200, `meta.hasMore` с подсказкой, `snippet` как есть в JSON и без тегов в human; unit-тесты, включая usage-error до API.
- [x] 3.4 `kb:article:show ID [--markdown]`: профиль `kb-article`, `--markdown` выводит содержимое байт в байт и несовместим с `--json`/`--plain`, `article-not-found`; unit-тест сравнивает вывод `--markdown` с fixture побайтно.
- [x] 3.5 Документация чтения: новый `docs/knowledge-base.md` (команды, граница «только База знаний 2.0», неполные результаты), строки в `docs/cli-command-map.md`; примеры из документа совпадают с help команд.

## 4. Запись: KB-04, KB-05

- [x] 4.1 Ввод записи: `--collection`, `--parent`, `ID` — положительные целые; `--title` 1…255 символов после обрезки; `--markdown-file PATH|-` с лимитом 1 048 576 байт без изменения содержимого; `update` требует `--title` или `--markdown-file`; unit-тесты usage-error/exit 2 до credentials.
- [x] 4.2 `kb:article:add`: проверка дубля по дереву (`article-exists`, `search-incomplete`, exit 4; `--allow-duplicate-title`), один вызов `note.document.add`, сверка ответа (`mismatch` → exit 3, `markdownNormalized`), `outcomeUnknown` без повтора, dry-run с `markdownBytes`/`markdownSha256`; unit-тесты на каждую ветку.
- [x] 4.3 `kb:article:update`: один вызов `note.document.update`, `overwrite` только с `--overwrite`, сверка ответа, подсказка про `--overwrite` для неизвестной ошибки записи `markdown`, `outcomeUnknown`, dry-run; unit-тесты, включая отсутствие поля `overwrite` в payload без флага.
- [x] 4.4 Документация записи в `docs/knowledge-base.md`: сценарий агента `show --markdown` → правка → `update --markdown-file`, замена текста целиком, защита от дубля и её граница (архивные статьи), `--overwrite`; строки в `docs/cli-command-map.md`.

## 5. JTBD, README и changelog

- [x] 5.1 `docs/task-jtbd.md`: раздел KB-01…KB-05 со статусом, командами и ссылками на тесты, обновлённые счётчики каталога и снимок; сверить с `CommandCatalog` и `tests/Integration/`.
- [x] 5.2 README: раздел о Базе знаний 2.0 и требуемом scope `note`; `CHANGELOG.md` → `Unreleased` со ссылкой на #234.

## 6. Проверка

- [x] 6.1 Integration: `tests/Integration/KnowledgeBase/*` на тестовом портале — временная база знаний с уникальным названием создаётся через SDK и переносится в корзину в `tearDown`; покрыты `kb:list`, `kb:article:list`, `kb:article:search`, `kb:article:show --markdown`, `kb:article:add` (включая `article-exists` при повторе), `kb:article:update`.
- [ ] 6.2 Live-приёмка конфликта совместного редактора: открыть статью в редакторе портала с несохранёнными правками, вызвать `kb:article:update --markdown-file` без `--overwrite`, снять код ошибки и закрепить его как `article-conflict` (exit 4) с unit-тестом; если воспроизвести не удалось — зафиксировать это в `verification.md`.
- [x] 6.3 Unit-тесты, php-cs-fixer, phpstan, rector, `openspec validate add-knowledge-base-articles --strict` проходят в Docker-окружении.
- [x] 6.4 `verification.md` с результатами, версией портала и границами проверки.

## Workflow follow-up

- Подготовить MR в репозиторий скилла [mesilov/bitrix24-cli-skill](https://github.com/mesilov/bitrix24-cli-skill): переименовать скилл `bitrix24-tasks` в `bitrix24-cli`, добавить команды `kb:*` и сценарий «прочитать → изменить → записать» для обновления статьи.
- После merge MR в `dev` и закрытия #234 архивировать change и обновить путь в разделе `## OpenSpec` issue.
