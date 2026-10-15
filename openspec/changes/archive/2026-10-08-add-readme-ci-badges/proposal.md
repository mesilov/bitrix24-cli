# Proposal

## Why

README не показывает статус уже действующих CI-линтеров. Четыре бейджа позволят сразу увидеть результат проверок и открыть их логи.

## What Changes

- Добавить под заголовком README бейджи лицензий, PHP-CS-Fixer, PHPStan и Rector.
- Указать `branch=dev&event=push` в изображениях и те же фильтры в ссылках на workflows.

## Capabilities

### New Capabilities

Нет. Изменение документации: `skip_specs: true`.

### Modified Capabilities

Нет: поведение CLI и CI остаётся прежним.

## Impact

Только `README.md` и артефакты этого change. Существующие правки основного checkout сохраняются.

## GitHub issues

- [#7 — Добавить в README бейджи CI-линтеров](https://github.com/mesilov/bitrix24-cli/issues/7): четыре бейджа существующих workflows и ссылки на проверки `dev`. Milestone: `0.1.0`; label: `documentation`.
