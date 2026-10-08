# Verification: prepare-worktree-development

Дата: 2026-10-08. Связанные артефакты: issue #2, MR #3.
Итоговая проверенная реализация: `d5838d64fdc91211e064b38fb63e534becba7887`, после интеграции Alpine из `dev` (`903dc64`).

| Измерение | Результат |
| --- | --- |
| Полнота | 13/13 задач; реализации всех 6 требований найдены |
| Корректность | 6/6 требований и 7/7 сценариев покрыты локальной интеграционной приёмкой |
| Согласованность | Решения design соблюдены; Make-workflow сохранён, PHP-окружение соответствует принятой Alpine spec и Composer lock |

## Соответствие требованиям

| Требование | Реализация и проверка |
| --- | --- |
| Stable checkout identity | `scripts/compose.sh`: SHA-256 физического пути; тест проверяет повторный вызов, одинаковые basename, пробелы, символьную ссылку и `.git`-файл |
| Independent build and runtime resources | Wrapper задаёт проект/файл/directory; общий image tag убран; тест сравнивает project labels, image tags, метки различимых сборок, mounts и сохранение контейнера B после down/restart A |
| Worktree-local writable state | Compose bind mount выбранного checkout; UID/GID определяются также при прямом вызове wrapper; тест проверяет владельца файлов и неизменность source/vendor/.env B после записи и установки зависимостей в A |
| Existing Make workflow compatibility | Все команды Make используют wrapper; docker-init, composer-install/dumpautoload/validate, cli/check и lifecycle проходят в worktree; обычный checkout с `.git`-каталогом также проверен |
| Documented setup and safe cleanup | README содержит setup, direct wrapper, cleanup, миграцию/перемещение; `git check-ignore` подтверждает игнорирование worktree и тестовых логов; ни секреты, ни vendor не копируются при создании checkout |
| Reproducible concurrent acceptance check | `make test-worktree`; временные detached worktree, параллельные build/install/up/check, targeted cleanup и сохранение логов; намеренная ошибка завершилась ненулевым кодом с `cleanup=true` |

## Первоначальная приёмка до миграции Alpine

- macOS arm64, Docker Desktop: `make test-worktree`, Compose 5.3.1 — PASS, exit 0, `cleanup=true`; результат `run.db2yQk`.
- macOS arm64: `WORKTREE_TEST_FAIL_AFTER_START=1 bash tests/worktree-isolation.sh` — ожидаемый exit 1 на этапе intentional failure, `cleanup=true`; результат `run.Op9Gxi`.
- Обычный чистый локальный clone с `.git`-каталогом: `make worktree-info docker-init docker-up cli check`, затем targeted cleanup — PASS. Незакоммиченные файлы пользовательского основного checkout не менялись.
- Linux arm64: тот же тест в Linux-контейнере с Docker CLI/Compose 5.6.0, отдельным чистым clone и доступом к тому же Docker Desktop daemon — PASS, exit 0, `cleanup=true`; результат `run.ngpiBO`. Это проверка Linux runtime для инструментов, а не отдельного физического Linux-хоста.
- `sh -n scripts/compose.sh`, `bash -n tests/worktree-isolation.sh`, dry run Make-целей, `git diff --check`, `openspec validate prepare-worktree-development --strict` — PASS.

## Первоначальный CI-сбой (устранён)

Первоначально дополнительная проверка Ubuntu в GitHub Actions не прошла: сборка неизменённого базового PHP Dockerfile получила HTTP 504 от `pecl.php.net` при скачивании `excimer`/`yaml`. Повторный запуск подтвердил ошибку скачивания до запуска окружения; на этом раннем коммите успешный CI на Ubuntu/amd64 не был подтверждён. Это не ошибка проверки изоляции и не заменяется локальными PASS.

Логи сохранены workflow как `worktree-isolation-linux`:
[CI run](https://github.com/mesilov/bitrix24-cli/actions/runs/37750227029).

Первоначальное ограничение CI закрыто результатами ниже. Критических расхождений с шестью требованиями спеки не найдено.

## Первое успешное завершение CI (Debian)

Проверенная реализация после интеграции `dev`: `408193519ac44b9d4ac2698dbc6963173ab3667a`.

- excimer 1.2.6 и yaml 2.3.0 устанавливаются из официальных upstream-репозиториев по полным commit SHA; набор runtime-модулей проверяется тестом.
- Конфликты с принятым MR линтеров разрешены; все новые Make-цели используют общий изолированный Compose wrapper.
- Path filters Worktree isolation удалены; проверка запускается на каждом коммите PR, включая docs/OpenSpec-only изменения, без дополнительного feature push run.
- macOS arm64: полный test-worktree после merge с dev — PASS, cleanup=true (`run.lF9IqI`); `make docker-init lint-all` — PASS.
- Ubuntu/amd64 GitHub Actions: [Worktree isolation](https://github.com/mesilov/bitrix24-cli/actions/runs/37752346992) — success, cleanup=true; версии расширений подтверждены в обеих сборках.
- GitHub checks на этом SHA: Worktree isolation и по два push/PR check для composer-license-checker, PHPStan, PhpCsFixer и Rector — все 9 success.

## Итоговая приёмка на принятом Alpine-окружении

- Включён актуальный `dev` с MR #9: Dockerfile сохранён из принятой миграции на `php:8.4-cli-alpine3.23`, без загрузок PECL. Проверка обязательных модулей (`bcmath`, `intl`) соответствует `docker-cli-environment`; `make check` проверяет platform requirements Composer lock.
- macOS arm64, Docker Desktop, Compose 5.3.1: полный `make test-worktree` на `d5838d6` — PASS, exit 0, `cleanup=true`; результат `run.BzsXJf`. Параллельные сборки, runtime labels/mounts, UID/GID, локальные файлы/зависимости и независимые down/restart проверены.
- `make docker-init lint-all` на Alpine — PASS: проверка лицензий, PhpCsFixer, PHPStan и Rector.
- Ubuntu/amd64 на отдельном GitHub Actions runner: [Worktree isolation](https://github.com/mesilov/bitrix24-cli/actions/runs/37753286234) — success на head SHA `d5838d64fdc91211e064b38fb63e534becba7887`; артефакт `run.qxkTAo/result.txt` подтверждает успешную приёмку и `cleanup=true`.
- Все 9 GitHub checks на этом HEAD завершились success; MR mergeable, merge state CLEAN.

Все 13 задач выполнены, покрыты 6 требований и 7 сценариев. Актуальная основная спецификация совпадает с delta requirements. Результаты CI для последующего коммита отчёта и архива фиксируются в описании MR, чтобы ссылка относилась к окончательному HEAD.
