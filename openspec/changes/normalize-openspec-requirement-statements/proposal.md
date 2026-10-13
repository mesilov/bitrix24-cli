# Proposal

## Why

После архива #96 strict-валидация canonical OpenSpec в dev завершается с пятью предупреждениями: requirement statements в четырёх capabilities длиннее 500 characters. Проверка на базе `f21e063` даёт 12 passed / 4 failed; пользователь поручил устранить все пять в отдельном worktree и открыть MR.

## What Changes

- Разделить пять составных descriptions на компактные requirement blocks, сохранив все исходные нормы.
- Сохранить имена пяти исходных requirements, их scenarios, Purpose каждой capability и незатронутые блоки.
- Перенести отдельные уже согласованные ограничения в ADDED вспомогательные requirements; это нормализация структуры спецификаций без изменения runtime/API admission.
- Зафиксировать before/after mapping, strict validation и проверку сохранности сценариев; добавить запись Unreleased.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `cli-experience`: разделить Explicit file and structured input — input modes, advanced mode, binary source и stdin ownership.
- `task-console-runtime`: разделить Exact MVP command registration и Policy covers the complete route plan — registry validation и выбранные route admissions.
- `task-mvp-scope`: разделить API admission and future lifecycle acceptance remain explicit — catalogue/lifecycle и узкие REST1 admissions.
- `connection-configuration`: разделить Published configuration schema — schema artifacts, structure coverage и shared fixtures.

## Impact

Меняются только OpenSpec и CHANGELOG. Базовые 16 specs остаются; исходные сценарии и API/CLI identifiers, числа 34/30/4 и 59/30/0/29, strict-rest3/task-v3 и узкие TAGS/Disk границы сохраняются. JTBD, runtime-код, schema JSON, зависимости и release scope не меняются. Historical archives/evidence остаются прежними.

Официальный documentation MCP ответил на search/details для tasks.task.get и disk.folder.uploadFile; это проверка имен/документации, не новое разрешение API или live acceptance. Контракты маршрутов сверены с CommandCatalog/ApiPolicyGuard.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/114 — документационное исправление пяти strict warnings, milestone 0.2.0.
- Follow-up: https://github.com/mesilov/bitrix24-cli/issues/96 и archive MR #112. Закрытый #96 не переоткрывается.

