# Proposal

## Why

Работа с шаблонами задач выделена из #61 в самостоятельную задачу, чтобы результаты задач можно было реализовать независимо.

## What Changes

- Отложенный change для исследования и последующего согласования сценариев работы с шаблонами.
- До отдельного запроса пользователя не реализовывать команды, не фиксировать неподтверждённые API payload.

## Capabilities

### New Capabilities

- `task-templates`: сценарии работы с шаблонами задач, требующие последующей детализации официальных API-контрактов.

### Modified Capabilities

Нет.

## Impact

Будущие CLI-команды, JTBD, API adapters и тесты. Реализация не входит в PR для #61.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/72 — отложенная работа с шаблонами задач, milestone 0.2.0.
- Выделено из https://github.com/mesilov/bitrix24-cli/issues/61.
