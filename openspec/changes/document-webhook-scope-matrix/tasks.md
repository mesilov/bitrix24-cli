# Tasks

## 1. Генератор матрицы

- [x] 1.1 Сверить ручную карту условий опций из design со всеми `routes()`, где состав зависит от запроса (`ChatCalls`, `ChatCreate`, `ChatDestination`, `ChatMessages`, `ChatSend`, `AddTask`, `AttachTaskFiles`, `FindTasks`, `ListTasks`/`TaskListRoute`, `ShowTask`, `UpdateTask`, `TaskHandover`). Зафиксировать окончательный список пар команда/scope/условие. Проверка: в карте есть каждая пара, у которой scope вне `task|tasks` появляется только при опции; расхождения с design исправлены в design.
- [x] 1.2 Добавить класс `ScopeMatrix` без ввода-вывода. Он строит markdown-блок по `ScopeManifest` и `CommandCatalog::ROUTES`: группировка по пространствам имён, фиксированный порядок групп scope, альтернативы через ` / `, маршруты с версией API, отдельные столбцы условий опций и необязательных источников, вычисляемый перечень команд без обращения к порталу. Проверка: unit-тест на детерминированность и на строки `task:list`, `task:file:attach`, `task:chat:list`, `auth:status`, `config:*`.
- [x] 1.3 Добавить `scripts/scope-matrix.php` с режимами `--write`/`--check`. Скрипт заменяет только текст между `<!-- scope-matrix:start -->` и `<!-- scope-matrix:end -->` в `docs/auth-scopes.md`. Добавить цели `scope-matrix` и `scope-matrix-check` в Makefile и в `make help`. Проверка: `make scope-matrix-check` завершается с кодом 0 после `make scope-matrix` и с кодом 1 и подсказкой после ручной правки блока; без маркеров — понятная ошибка.

## 2. Документация

- [x] 2.1 В `docs/auth-scopes.md` добавить раздел «Матрица разрешений» с маркерами и сгенерировать его через `make scope-matrix`. Над блоком — пояснения: как читать альтернативы и условия, что матрица описывает текущую сборку, что scope не даёт прав на объект. Проверка: `make scope-matrix-check` — код 0; каждая команда из `CommandCatalog::ROUTES` есть в блоке.
- [x] 2.2 В `docs/auth-scopes.md` добавить раздел «Если портал отказывает в доступе»: где менять права вебхука, таблица «Признак → Причина → Что делать» для `scope-missing`, `permission-denied` с `insufficient_scope` и без него, `policy-denied` и строки `This operation used an explicitly admitted REST 1.0 route.`, затем самодиагностика через `auth:status`. Проверка: тексты признаков совпадают с фактическими сообщениями в `SdkApiTransport`, `ScopeCheckingTransport`, `ResultPresenter` (`rg` по каждой строке).
- [x] 2.3 В README в разделе о подключении добавить таблицу «Код scope | Название в настройках вебхука | Что без него не работает» для `task`, `user` (`user_basic`, `user_brief`), `im`, `disk`, `department`, `sonet_group`, `call`, рекомендуемый набор и ссылку на матрицу. Писать без упоминаний прежнего поведения. Проверка: названия совпадают со справочником scope из proposal; ссылка ведёт на якорь матрицы.
- [x] 2.4 В `docs/development.md` рядом с `cli-reference` описать `make scope-matrix`/`scope-matrix-check` и напомнить, что при новой опции, добавляющей scope, нужно обновить карту условий. Проверка: раздел ссылается на скрипт и цели Makefile.
- [x] 2.5 Добавить запись в `CHANGELOG.md` → `Unreleased` со ссылкой на #178. Проверка: `git diff origin/dev...HEAD -- CHANGELOG.md` содержит запись.

## 3. Проверки

- [x] 3.1 Unit-тест сверки: блок в `docs/auth-scopes.md` равен выводу `ScopeMatrix`; каждая пара из карты условий есть среди обязательных групп команды по манифесту; README упоминает каждый код scope из манифеста и ссылается на матрицу. Проверка: тест падает при ручной правке блока, при устаревшей пометке и при удалении кода из README; после возврата — зелёный.
- [x] 3.2 Прогнать `make test` (или эквивалент из `docs/development.md`), PHPStan, php-cs-fixer, rector и `make cli-reference-check`. Проверка: все команды завершились с кодом 0, вывод сохранён в отчёте MR.
- [x] 3.3 `openspec validate document-webhook-scope-matrix --strict`. Проверка: без ошибок и предупреждений.

## Workflow follow-up

- Добавить в issue #178 раздел `## OpenSpec` с идентификатором и путём change, после архивирования обновить путь.
- Сообщить в #152 / change `explain-webhook-setup`, что таблицу прав нужно заменить ссылкой на README и матрицу.
- После merge предложить в репозитории bitrix24-cli-skill ссылку на матрицу.
