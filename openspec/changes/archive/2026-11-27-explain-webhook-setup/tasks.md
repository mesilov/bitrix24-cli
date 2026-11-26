# Tasks

## 1. Руководство по созданию вебхука

- [x] 1.1 Добавить в `docs/auth.md` перед «Первый запуск» раздел «Где взять входящий вебхук» с якорем `<a id="incoming-webhook"></a>` (design.md, решения 1, 2, 5, 6). Состав:
  - шаги создания по официальной статье;
  - случаи «нет пункта» и «REST недоступен на тарифе»;
  - вид URL с вымышленным доменом и заполнителем кода;
  - причина скрытого ввода;
  - перевыпуск через *Интеграции* → *Перегенерировать* и `auth:login NAME --replace`;
  - ссылка на права из #178;
  - ссылки на `apidocs.bitrix24.ru`.

  Проверка: по тексту читаются сценарии «New user follows the login docs», «Webhook option is unavailable», «URL shape without real secrets», «Why input is hidden», «Rotate a leaked or expired webhook»; `rg -n '/rest/[0-9]+/[a-z0-9]{8,}' docs README.md` не находит реальных кодов.
- [x] 1.2 В README, раздел «Подключения и конфигурация», добавить к фразе о вебхуке ссылку на `docs/auth.md#incoming-webhook`; блоки команд не менять. Проверка: ссылка ведёт на существующий якорь, `HelpAndAboutTest` проходит.

## 2. Справка и интерактивный запрос auth:login

- [x] 2.1 Дополнить начало `setHelp` в `LoginCommand` абзацем из design.md, решение 4. Проверка: тест `auth:login --help` и `help auth:login` — код 0, одинаковый вывод, путь меню и ссылка `#incoming-webhook`, без чтения credentials и API-вызовов; `RussianHelpTest` проходит.
- [x] 2.2 В `AuthCommandRunner::secret()` в интерактивном пути выводить в stderr трёхстрочную подсказку и задавать вопрос `URL входящего вебхука: ` (design.md, решение 3). Проверка: unit-тест интерактивного входа и `--replace` — подсказка и вопрос в stderr, stdout пуст, fixture-секрет не появляется ни в одном потоке.
- [x] 2.3 Покрыть тестами отсутствие подсказки при `--webhook-stdin`, при `-n` без `--webhook-stdin` (usage-error, exit 2), для существующего NAME без `--replace`, для отсутствующего NAME с `--replace` и при `strict-rest3` без `--skip-check`. Обновить проверку в `ConnectionProfilesTest` (сейчас ищет `HTTPS incoming webhook:`) на новую строку и подсказку. Проверка: `vendor/bin/phpunit tests/Unit/ConnectionProfilesTest.php` и полный набор unit-тестов проходят.
- [x] 2.4 Описать в разделе `#incoming-webhook` вид интерактивного запроса и добавить запись в `CHANGELOG.md` (`Unreleased`) со ссылкой на #152, отметив новую русскую строку запроса. Проверка: `git diff origin/dev...HEAD -- CHANGELOG.md` содержит запись о #152.

## 3. Интеграционная проверка

- [x] 3.1 Прогнать PHPUnit, PHPStan, PHP-CS-Fixer, Rector в режиме dry-run и `openspec validate explain-webhook-setup --strict`. Проверка: все команды успешны, вывод сохранён в отчёт verify.
- [ ] 3.2 Приёмка на тестовом портале: пройти раздел `#incoming-webhook` с нуля (пункты меню, *Настройка прав*, *Вебхук для вызова rest api*, *Перегенерировать*), выполнить интерактивный `auth:login` и `auth:status`. Проверка: названия пунктов совпадают с документацией или документация исправлена; evidence без вебхуков и секретов.
- [x] 3.3 Перед MR синхронизироваться с `origin/dev` и проверить ссылки на права из #178 (design.md, решение 6): если PR #232 влит — ссылки на «Права вебхука» и `docs/auth-scopes.md#матрица-разрешений`, иначе — на `docs/auth-scopes.md`. Проверка: каждая ссылка из раздела, README и справки ведёт на существующий файл и заголовок.
- [x] 3.4 Проверить трассировку: раздел `## GitHub issues` в proposal с #152, раздел `## OpenSpec` в issue #152 с `explain-webhook-setup` и путём, milestone и метка у issue. Проверка: `gh issue view 152 --json body,milestone,labels`.

## Workflow follow-up

- MR из `feature/152-webhook-setup-guide` в `dev` с проверкой CHANGELOG перед открытием и перед merge.
- После merge и закрытия #152 — архивировать change, обновить путь в issue, удалить worktree.
