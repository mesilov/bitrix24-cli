# Tasks

Исследование API и портальная проверка уже выполнены: [research.md](research.md). Ниже — реализация; отметки ставятся по факту кода и проверок.

## 1. Адаптер прежней ленты комментариев

- [x] 1.1 Добавить порт и REST1-адаптер `task.commentitem.getlist` с запросом страницы по курсору: `TASKID`, всегда присутствующий `ORDER {ID: asc|desc}`, `FILTER` с `>ID` или `<ID`; DI-регистрация. Проверка: unit-тесты адаптера фиксируют точные параметры запроса для asc/desc, первой страницы без фильтра и обоих курсоров; `start` не передаётся.
- [x] 1.2 Нормализовать комментарий в `id`, `authorId`, `authorName`, `createdDate`, `text`, `attachments[{attachmentId, fileId, name, size}]` с приведением строковых ID к int; отбросить `AUTHOR_EMAIL`, `POST_MESSAGE_HTML`, `DOWNLOAD_URL`, `VIEW_URL`. Проверка: unit-тест на фикстуре из research (строки, `null` HTML, вложение с URL, содержащим `auth[ap]`) — нормализованный результат не содержит URL и токена; отсутствие `ATTACHED_OBJECTS` даёт пустой список; некорректный ID отклоняется как неверная форма ответа.

## 2. Команда `task:legacy:comment:list`

- [x] 2.1 Добавить `ListTaskLegacyCommentsCommand`, request и input mapping: `TASK_ID`, `--limit`, `--all`, `--before`, `--after`, `--max-comments` (по умолчанию 10000), взаимоисключение `--before`/`--after`, валидация чисел. Проверка: command-тесты принимают допустимые комбинации и отклоняют неверные с exit 2 до API.
- [x] 2.2 Реализовать курсорный обход в сервисе: окно последних и `--before` через desc с разворотом, `--all` и `--after` через asc, остановка на пустой странице, на бюджете (`comment-budget-exhausted`) и при отсутствии прогресса (`pagination-no-progress`); metadata `source`, `complete`, `scanned`, `returned`, `hasMore`, `reason`. Проверка: unit-тесты на синтетических fixtures — одна страница, несколько страниц по 50, окно `--limit 20` из 120, `--after`, исчерпание бюджета (exit 3), повтор той же страницы (exit 3), пустая лента (exit 0).
- [x] 2.3 Обработать ошибки источника: `ERROR_METHOD_NOT_FOUND` и `ERROR_CORE` на первой странице → `legacy-comments-unavailable` с ненулевым кодом и без пустого успешного списка; ошибка страницы после успешной → partial `page-error`, exit 3; `permission-denied`, нехватка scope и Ctrl-C — общие коды. Проверка: unit-тесты каждого случая, JSON не содержит `data.items: []` при ошибке.
- [x] 2.4 Зарегистрировать команду в `CommandCatalog` с маршрутом `task.commentitem.getlist` v1 и effect `read`; обновить тесты каталога и scope до 51 task-команды. Проверка: тесты регистрации, `ScopeManifest` (scope `task`), `strict-rest3` → policy-denied с exit 4 до credentials/API, metadata маршрута с версией 1.0.
- [x] 2.5 Реализовать human/plain/JSON вывод: TSV-колонки `id`, `createdDate`, `authorId`, `authorName`, `text`, `attachmentCount`; human — заголовок о прежней ленте (legacy, только чтение) и таблица с числом вложений; `--plain` — пометка в stderr; JSON — `meta.legacy = true`; описание в `list` с `[legacy]` и предупреждение первой строкой help. Проверка: renderer/command-тесты трёх форматов и `list task`; ни один формат не содержит URL вложений.
- [x] 2.6 Добавить `tests/Integration/Task/ListTaskLegacyCommentsCommandTest.php` с группой `command:task:legacy:comment:list` (read-only happy-path на задаче с прежними комментариями из `B24CLI_*` настроек, пропуск без них) и синтетический fake-портал для unit-сценариев (`TaskLegacyCommentsTest`). Проверка: `IntegrationCommandCoverageTest` проходит; интеграционный тест пропускается без live-настроек.
- [x] 2.7 Help команды и документация: `docs/task-usage.md` (раздел прежних комментариев: отличие от чата, курсор, полнота, недоступность метода, REST1 и устаревший метод, отсутствие ссылок на вложения), `docs/cli-command-map.md` (новая строка, число публичных команд). Проверка: `make cli-reference-check` проходит, пример в help совпадает с документацией.

## 3. Признак в `task:chat:list`

- [x] 3.1 Добавить `meta.legacyComments = {status: not-checked, command: "task:legacy:comment:list <TASK_ID>"}` без дополнительных запросов; human — строка-подсказка после сообщений, `--plain` — в stderr. Проверка: `ListTaskChatCommand`/`TaskChatService`-тесты — тот же список вызовов API, те же items, `complete` и код выхода; признак присутствует и при полном, и при частичном чтении.
- [x] 3.2 Обновить help `task:chat:list` и раздел истории чата в `docs/task-usage.md`: полнота относится к чату, прежние комментарии читаются `task:legacy:comment:list`. Проверка: `make cli-reference-check` проходит.

## 4. JTBD и changelog

- [x] 4.1 Обновить `docs/task-jtbd.md`: в сценарии, использующие `task:chat:list` для истории обсуждения (PM-06, AM-06, EMP-07), добавить `task:legacy:comment:list` со ссылкой на интеграционный тест; новая строка сверки с базой `dev`, числом команд и границами live-проверки. Проверка: ID сценариев сохранены, ссылки на файлы существуют.
- [x] 4.2 Добавить запись в `CHANGELOG.md` → `Unreleased` со ссылкой на #182. Проверка: `git diff origin/dev...HEAD -- CHANGELOG.md` содержит запись.

## 5. Интеграционная проверка

- [x] 5.1 Запустить `make unit`, `make check`, `make lint-all`, `make cli-reference-check`, `git diff --check` в Docker-окружении. Проверка: все — exit 0.
- [x] 5.2 Live read-only на тестовом портале (вебхук из `.env.local` основного checkout): `task:legacy:comment:list 2 --all --json`, `--limit 2`, `--after 6`, `--before 8`, задача без комментариев, несуществующая задача; `task:chat:list 2 --all --json` с `meta.legacyComments`. Проверка: обезличенные результаты в `evidence/` change; вывод (включая `-vvv`) не содержит `auth[ap]`; непроверенные границы (размер страницы больше 50, пользователь без доступа к задаче) явно записаны.
- [x] 5.3 `openspec validate add-task-legacy-comments --strict --no-interactive` и `openspec-verify-change`: каждое требование обеих дельт сопоставлено с кодом и тестами. Проверка: validate — valid, задачи отмечены по факту.

## Workflow follow-up

- `add-weekly-task-report` не завершён (live-проверка 4.4), поэтому этот change архивирован раньше. Число команд в `Exact MVP command registration` сверено с `CommandCatalog`: 51 task-команда и пять users-команд (с `users:presence`); та же формулировка перенесена в дельту `add-weekly-task-report`.
- PR [#224](https://github.com/mesilov/bitrix24-cli/pull/224) влит в `dev` (`8013028`); путь в #182 обновлён на архивный.
