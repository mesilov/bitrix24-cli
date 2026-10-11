# Proposal

## Why

Текущий CLI запускается через PHP/Composer или Docker, что усложняет установку инструмента в терминале и CI. Standalone-бинарник позволит использовать команды Bitrix24 после скачивания одного архива, без подготовки PHP-окружения.

## What Changes

- Упаковка точки входа, исходников и production-зависимостей из `composer.lock` в PHAR и объединение с embedded PHP runtime через `static-php-cli`.
- Поддержка бинарников Linux amd64, macOS arm64 и Windows amd64 с документированными minimum OS/system requirements.
- Повторяемая сборка через Make/scripts, отдельные packaging jobs на PR/push/manual events, проверка артефактов вне checkout без внешнего PHP.
- Production CI по конкретному version tag на коммите из истории `main`, с платформенными архивами, `SHA256SUMS` и возобновляемой публикацией draft после успешной сборки и smoke tests всей матрицы.
- Developer Make builds на любом clean checkout commit без обязательного тега; dirty inputs и несовпадающий SHA отклоняются до staging.
- Документация установки в `PATH`, проверки checksum, сборочных входов и ограничений.

Образец обвязки: [yiipress/engine](https://github.com/yiipress/engine/tree/e61459fc788a74b91188d5d41a5fca59a02a229e), commit `e61459fc788a74b91188d5d41a5fca59a02a229e`, проверенный 11.10.2026. Адаптируются packaging/CI и подход PHAR → PHP micro → binary; специфичные extensions YiiPress не включаются. Nightly publication, self-update, установщики и distroless image остаются вне этой итерации.

## Capabilities

### New Capabilities

- `binary-distribution`: сборка, проверка и выпуск standalone CLI для поддержанных платформ, включая version identity, состав пакета и установку.

### Modified Capabilities

Нет. `docker-cli-environment` и `worktree-development` сохраняют текущие требования; упаковка добавляет самостоятельный способ распространения и соблюдает изоляцию writable state.

## Impact

- `bin/console` и версия приложения: корректная загрузка из PHAR и идентичность tag/commit без изменения контрактов команд.
- Новые `build/` scripts, packaging Docker stages, Make targets, GitHub Actions и generated `dist/`/cache paths.
- Embedded PHP 8.4/8.5 с extensions по production lock, PHAR/compression и HTTPS CA support; `static-php-cli` закрепляется по commit, PHP — по точной версии.
- При переносе исходников YiiPress сохраняются BSD-3-Clause notices. Rust/highlighter/Markdown modules из образца нашему runtime не требуются.
- База планирования: `dev` commit `5d236a084f67b9de2ef99fa99ad3fc586582c20b`; при реализации упаковывается актуальный runtime, включая принятые к тому времени команды задач.

## GitHub issues

- [#14 — Добавить сборку standalone-бинарников по примеру YiiPress](https://github.com/mesilov/bitrix24-cli/issues/14): весь объём `binary-distribution`; milestone `0.1.0`, label `enhancement`.
