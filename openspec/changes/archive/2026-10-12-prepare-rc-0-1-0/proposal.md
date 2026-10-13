# Proposal

## Why

RC 0.1.0 нужно собрать из актуальной `dev` с завершённой трассировкой issues/OpenSpec и документацией первой сборки. В milestone уже закрыты 11 issues, но два завершённых changes ещё активны, CHANGELOG.md отсутствует, а README содержит устаревшие migration notes и ссылки на раннюю сборку.

## What Changes

- Проверить фактическую поставку каждого issue 0.1.0 и зафиксировать аудит; сохранить открытые задачи 0.2.0 и post-MVP.
- Проверить и заархивировать `add-ci-linters` (10/10) и `design-task-console-architecture` (42/42), синхронизировать их delta specs и обновить ссылки в текущей документации и GitHub issues.
- Создать CHANGELOG.md: 0.1.0 как первая сборка RC с возможностями, платформами и действующими ограничениями.
- Переписать README вокруг текущих команд и конфигурации, убрать обратную совместимость и миграцию; закрепить этот стиль документации до 1.0.0.
- Выполнить локальные проверки, поставить через PR в `dev` и подтвердить native CI matrix выбранного source SHA.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

Нет. `skip_specs: true`: это документация и завершение существующих changes, без изменений CLI/API/build contracts. При архивировании синхронизируются уже утверждённые delta specs исходных changes.

## Impact

README.md, CHANGELOG.md, AGENTS.md, docs/rc-0.1.0.md, OpenSpec specs/archive и обратная трассировка issues. Источник — `dev d145b226fd048423c2ce2af93b28efd733d4ccec`. Работа в отдельном worktree `feature/47-prepare-rc-0-1-0`; основной dirty checkout сохраняется. Тег версии и GitHub Release не создаются; dev artifacts сохраняют `dev-<full SHA>` по текущему packaging contract. Новые portal/fork acceptance не входят в задачу.

## GitHub issues

- [#47 — Подготовить RC 0.1.0 в dev](https://github.com/mesilov/bitrix24-cli/issues/47), milestone 0.1.0, label documentation: весь release-readiness audit и документация.
- [#4](https://github.com/mesilov/bitrix24-cli/issues/4) и [#10](https://github.com/mesilov/bitrix24-cli/issues/10): архивирование завершённой области и обновление issue paths; перенесённые проверки сохраняются в #41/#42, milestone 0.2.0.
