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

## Delivery and completion

- Implementation: PR #91 merged in dev at `4406a8354e6e2439f90c1f0f1dfc3a15e8fd5ea2`.
- Installer publication: https://github.com/mesilov/bitrix24-cli/pull/93 merged in main at `fd86141169eed9cfe2a8b6b6c18a68310d209040`, with 19/19 promotion checks passing.
- Anonymous public script installation: latest and explicit public Release `0.1.0`, native Linux/macOS main CI and local macOS shell/profile/configuration checks passed. See `public-installation-evidence-2026-10-13.json`.
- Completed change archive: `openspec/changes/archive/2026-10-13-add-shell-installer/`; all 10 tasks complete and the 8 installer requirements are synchronized into `openspec/specs/binary-distribution/spec.md` when archived.
