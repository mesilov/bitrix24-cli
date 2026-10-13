# Proposal

## Why

Ручной запуск и установка из #24 требуют нескольких shell-команд. Пользователю нужен installer, который сам выбирает standalone archive, проверяет checksum, устанавливает CLI и подготавливает запуск `b24cli` из любого каталога.

## What Changes

- Добавить POSIX `install.sh` для существующих Unix targets: macOS arm64 и Linux amd64; пользовательская установка без sudo и внешнего PHP-окружения.
- Скачивать assets публичного GitHub Release: latest stable по умолчанию, конкретный tag через `--version`; проверять SHA-256 до распаковки.
- Устанавливать binary и notices; проверять версию новой сборки перед заменой существующей установки, сохранять пользовательские данные.
- Создавать env-файл и идемпотентно настраивать zsh/bash; учитывать ZDOTDIR, `--install-dir` и `--no-modify-path`.
- Показать пользователю подключение env-файла для текущего терминала и готовность новых сессий. Скрипт через `| sh` не меняет окружение родительского shell.
- Подготовить README, fixture tests, CI acceptance и проверку публичного URL скрипта/релиза.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `binary-distribution`: добавить требования автоматической Unix установки, выбора Release, сохранности обновления и shell integration.

## Impact

Новый `install.sh`, установка/acceptance tests, README/build documentation и CI. Существующие platform archives и `SHA256SUMS` подходят для installer. Публичный release с этими assets — условие рабочего сценария; его публикация не выполняется при планировании. Windows installer, новые targets, Homebrew и self-update CLI не входят в эту итерацию.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/28 — installer для milestone `0.2.0`, labels `enhancement`, `documentation`.
- https://github.com/mesilov/bitrix24-cli/issues/14 — существующая сборка standalone archives.
- https://github.com/mesilov/bitrix24-cli/issues/24 — ручной первый запуск и PATH инструкция.
- https://github.com/mesilov/bitrix24-cli/issues/23 и https://github.com/mesilov/bitrix24-cli/issues/25 — macOS launch blocking и исследование signing/notarization; отдельные задачи.
