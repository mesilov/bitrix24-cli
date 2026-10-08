# Proposal

## Why

В bitrix24-cli пока нет GitHub Actions для проверки лицензий зависимостей, стиля PHP, статического анализа и рекомендаций Rector. Перенос проверок из b24phpsdk позволит автоматически обнаруживать нарушения при push и pull request и воспроизводить их локально.

## What Changes

- Адаптировать четыре workflows ветки `v3` b24phpsdk: `license-check.yml`, `php-cs-fixer.yml`, `phpstan.yml`, `rector.yml`.
- Добавить необходимые Composer dev-зависимости, конфигурации инструментов и Make-цели `lint-allowed-licenses`, `lint-cs-fixer`, `lint-phpstan`, `lint-rector`; объединить эти четыре проверки в `lint-all`.
- Настроить проверки на структуру CLI, PHP 8.4 и существующий Docker Compose; использовать `LOCAL_UID`/`LOCAL_GID` и сборку собственного образа на runner.
- Запускать проверки при `push` и `pull_request`; ошибки должны завершать соответствующую проверку с ненулевым кодом. PHP-CS-Fixer и Rector работают в режиме проверки без изменения исходников.
- Документировать локальный запуск и подтвердить работу всех четырёх проверок в GitHub Actions.

## Capabilities

### New Capabilities

- `ci-quality-checks`: автоматические и локально воспроизводимые проверки лицензий, стиля, типов и правил Rector для CLI.

### Modified Capabilities

Нет: актуальные спецификации пока отсутствуют.

## Impact

`.github/workflows/`, `composer.json`, `composer.lock`, `Makefile`, `.allowed-licenses.php`, `.php-cs-fixer.php`, `phpstan.neon.dist`, `rector.php`, `.gitignore`, `README.md`. Возможны минимальные исправления найденных нарушений в коде CLI без изменения публичного поведения. Использовать итоговый Make/Compose workflow задачи #2; изоляция worktree остаётся в её области. Тестовые pipelines SDK, Deptrac, публикация образов и релизный pipeline не входят в эту задачу.

## Sources

Для воспроизводимой сборки на CI также затронут `docker/php-cli/Dockerfile`: текущие версии удалённых расширений `excimer`/`yaml` закрепляются по SHA официальных GitHub-исходников вместо недоступных PECL REST metadata. Набор расширений сохраняется.

Проверено 2026-10-08. Источник: `bitrix24/b24phpsdk`, ветка `v3`, commit `8ebd4c154d5557db949b0196825f347a3a6c5bf0`.

- https://github.com/bitrix24/b24phpsdk/blob/v3/.github/workflows/license-check.yml
- https://github.com/bitrix24/b24phpsdk/blob/v3/.github/workflows/php-cs-fixer.yml
- https://github.com/bitrix24/b24phpsdk/blob/v3/.github/workflows/phpstan.yml
- https://github.com/bitrix24/b24phpsdk/blob/v3/.github/workflows/rector.yml

## GitHub issues

- [#4 — Добавить CI-линтеры на базе b24phpsdk v3: лицензии, PHP-CS-Fixer, PHPStan, Rector](https://github.com/mesilov/bitrix24-cli/issues/4): change покрывает четыре workflows, их инструменты/конфиги, локальный Make workflow и проверку работы в Actions. Milestone: `0.1.0`; label: `enhancement`.
