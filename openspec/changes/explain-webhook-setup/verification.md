# Verification

Проверено 2026-11-26 на ветке `feature/152-webhook-setup-guide`, база `origin/dev` 3f4c21b (после merge #232 по #178). Изменения не закоммичены.

## Автоматические проверки (задача 3.1)

Все команды выполнены в Docker-окружении worktree (`scripts/compose.sh`, PHP 8.4.28).

| Команда | Результат |
| --- | --- |
| `make unit` | OK, 1409 тестов, 22035 assertions |
| `make lint-cs-fixer` | OK после применения `new class ()` в `WebhookPromptTest` |
| `make lint-phpstan` | OK |
| `make lint-rector` | OK после переименования переменных в `WebhookPromptTest` |
| `make lint-allowed-licenses` | OK |
| `make composer-validate` | OK |
| `make cli-reference-check` | OK, карта соответствует 76 публичным командам |
| `make scope-matrix-check` | OK |
| `openspec validate explain-webhook-setup --strict` | valid |

Покрытие сценариев тестами:

- `WebhookPromptTest::testInteractiveTerminalShowsTheHintBeforeTheHiddenRussianQuestion` — три строки подсказки до вопроса, вопрос `URL входящего вебхука: `, скрытый ввод без fallback, всё в error output («Interactive prompt»).
- `WebhookPromptTest::testNoTerminalOrNonInteractiveInputFailsBeforeAnyOutput` — без TTY или при `-n` usage-error/exit 2 без вывода.
- `ConnectionProfilesTest::testWebhookPromptIsAbsentForPipedInputAndFailuresBeforeInput` — нет подсказки при `-n` без `--webhook-stdin`, при `--webhook-stdin --skip-check` и при `--api-policy strict-rest3` без `--skip-check` («Piped or noninteractive input», «Errors before input»).
- `ConnectionProfilesTest::testExistingProfileRejectsLoginBeforeWebhookInputAndPreservesFiles` — нет подсказки для существующего NAME без `--replace` и для отсутствующего NAME с `--replace`.
- `ConnectionProfilesTest::testHelpExplainsLoginWithoutConfigurationOrCredentials` — `help auth:login` и `auth:login --help` совпадают, начинаются с пути меню, причины скрытого ввода и ссылки `#incoming-webhook`; без конфигурации и API-вызовов («Read login help without a connection»).
- `RussianHelpTest` — справка проходит языковой контракт.

## Настоящий терминал

`bin/b24cli auth:login demo --config-dir <пустой каталог>` запущен в псевдотерминале (PHP `proc_open` с pty-дескрипторами внутри контейнера) с вводом Ctrl-D без секрета:

```text
Вставьте URL входящего вебхука Битрикс24. Ввод скрыт: в адресе есть секретный код.
Где взять: Разработчикам → Готовые сценарии → Другое → Входящий вебхук, поле «Вебхук для вызова rest api».
Пошаговая инструкция и права: https://github.com/mesilov/bitrix24-cli/blob/main/docs/auth.md#incoming-webhook
URL входящего вебхука: usage-error: Неверные аргументы или параметры команды. Пример: b24cli auth:login work. Справка: b24cli auth:login --help.
exit=2, файлов в каталоге конфигурации: 0
```

Сценарий «Interactive replacement» в настоящем терминале не запускался: `--replace` проходит через тот же `WebhookPrompt::ask()`, что подтверждено чтением кода.

## Ссылки (задача 3.3)

После merge PR #232 разрешаются все ссылки: `../README.md#права-вебхука`, `auth-scopes.md#матрица-разрешений`, `auth-scopes.md#если-портал-отказывает-в-доступе`, `#хранение-секрета`, `#incoming-webhook` из `docs/auth.md`; `docs/auth.md#incoming-webhook` и `#права-вебхука` из README; ссылка из CHANGELOG. `rg -n '/rest/[0-9]+/[a-z0-9]{8,}' docs README.md` реальных кодов не находит. Англоязычная документация упомянута ссылкой на корень `apidocs.bitrix24.com`: путь английской статьи через MCP не проверялся.

## Трассировка (задача 3.4)

- `proposal.md` → `## GitHub issues`: #152.
- Issue #152 → `## OpenSpec`: `explain-webhook-setup`, `openspec/changes/explain-webhook-setup/`, ветка `feature/152-webhook-setup-guide`; milestone 0.3.0; метка `documentation`.

## Не проверено (задача 3.2)

Приёмка на портале не выполнена: пункты меню *Разработчикам → Готовые сценарии → Другое → Входящий вебхук*, *Настройка прав*, *Вебхук для вызова rest api*, *Интеграции* и *Перегенерировать* взяты из официальной статьи (проверка MCP 2026-11-26) и в интерфейсе портала не сверялись. Интерактивный вход с реальным вебхуком не выполнялся.
