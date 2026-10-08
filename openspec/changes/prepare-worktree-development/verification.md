# Verification: prepare-worktree-development

Дата: 2026-10-08. Связанные артефакты: issue #2, MR #3.
Проверенная реализация: `8d6081c6240177c3569fef12a8b7f61260445884`.

| Измерение | Результат |
| --- | --- |
| Полнота | 9/9 задач; реализации всех 6 требований найдены |
| Корректность | 6/6 требований и 7/7 сценариев покрыты локальной интеграционной приёмкой |
| Согласованность | Все 6 решений design соблюдены; Make-workflow и Dockerfile сохранены |

## Соответствие требованиям

| Требование | Реализация и проверка |
| --- | --- |
| Stable checkout identity | `scripts/compose.sh`: SHA-256 физического пути; тест проверяет повторный вызов, одинаковые basename, пробелы, символьную ссылку и `.git`-файл |
| Independent build and runtime resources | Wrapper задаёт проект/файл/directory; общий image tag убран; тест сравнивает project labels, image tags, метки различимых сборок, mounts и сохранение контейнера B после down/restart A |
| Worktree-local writable state | Compose bind mount выбранного checkout; UID/GID определяются также при прямом вызове wrapper; тест проверяет владельца файлов и неизменность source/vendor/.env B после записи и установки зависимостей в A |
| Existing Make workflow compatibility | Все команды Make используют wrapper; docker-init, composer-install/dumpautoload/validate, cli/check и lifecycle проходят в worktree; обычный checkout с `.git`-каталогом также проверен |
| Documented setup and safe cleanup | README содержит setup, direct wrapper, cleanup, миграцию/перемещение; `git check-ignore` подтверждает игнорирование worktree и тестовых логов; ни секреты, ни vendor не копируются при создании checkout |
| Reproducible concurrent acceptance check | `make test-worktree`; временные detached worktree, параллельные build/install/up/check, targeted cleanup и сохранение логов; намеренная ошибка завершилась ненулевым кодом с `cleanup=true` |

## Выполненные проверки

- macOS arm64, Docker Desktop: `make test-worktree`, Compose 5.3.1 — PASS, exit 0, `cleanup=true`; результат `run.db2yQk`.
- macOS arm64: `WORKTREE_TEST_FAIL_AFTER_START=1 bash tests/worktree-isolation.sh` — ожидаемый exit 1 на этапе intentional failure, `cleanup=true`; результат `run.Op9Gxi`.
- Обычный чистый локальный clone с `.git`-каталогом: `make worktree-info docker-init docker-up cli check`, затем targeted cleanup — PASS. Незакоммиченные файлы пользовательского основного checkout не менялись.
- Linux arm64: тот же тест в Linux-контейнере с Docker CLI/Compose 5.6.0, отдельным чистым clone и доступом к тому же Docker Desktop daemon — PASS, exit 0, `cleanup=true`; результат `run.ngpiBO`. Это проверка Linux runtime для инструментов, а не отдельного физического Linux-хоста.
- `sh -n scripts/compose.sh`, `bash -n tests/worktree-isolation.sh`, dry run Make-целей, `git diff --check`, `openspec validate prepare-worktree-development --strict` — PASS.

## Ограничение CI

WARNING: дополнительная проверка Ubuntu в GitHub Actions не прошла: сборка неизменённого базового PHP Dockerfile получила HTTP 504 от `pecl.php.net` при скачивании `excimer`/`yaml`. Повторный запуск подтвердил ошибку скачивания до запуска окружения; успешный CI на Ubuntu/amd64 не подтверждён. Это не ошибка проверки изоляции и не заменяется локальными PASS.

Логи сохранены workflow как `worktree-isolation-linux`:
[CI run](https://github.com/mesilov/bitrix24-cli/actions/runs/37750227029).

Критических расхождений с шестью требованиями спеки не найдено. Остаётся одно предупреждение: CI требует повторной проверки после восстановления доступности PECL. Слияние MR должно учитывать этот незакрытый CI-гейт.
