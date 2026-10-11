# Proposal

## Why

После скачивания архива zsh не находит `b24cli`: текущий каталог не входит в PATH. Короткий первый запуск и полная инструкция установки должны быть доступны прямо рядом со скачиванием.

## What Changes

- Добавить в README запуск `./b24cli --version` и `./b24cli --help` после проверки checksum и распаковки.
- Объяснить поиск команды в PATH и явный относительный путь `./b24cli`.
- Документировать полный macOS/zsh сценарий: checksum, распаковка, пользовательский bin directory, права, сохранение licenses, PATH текущей сессии и `.zshrc`.
- Проверить установку из другого каталога и новой zsh-сессии; дать ссылку на отдельный issue #23 о блокировке запуска macOS.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

Нет. Документация реализует существующее требование `binary-distribution → Installation and evidence documentation`; поведение CLI не меняется, `skip_specs: true`.

## Impact

`README.md`, `build/README.md` и OpenSpec evidence. PHP runtime, packaging, license files и бинарные assets не меняются. Milestone: `0.1.0`, label: `documentation`.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/24 — локальный первый запуск и установка в PATH.
- Связанный https://github.com/mesilov/bitrix24-cli/issues/23 — блокировка первого запуска macOS, отдельная задача.
