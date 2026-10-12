# Proposal

## Why

30 команд задач имеют собственные live happy-path, но дополнительные проверки прав и полноты данных остаются частичными. По решению пользователя от 12.10.2026 непроверенные части #10 переносятся в milestone 0.2.0 с сохранением явных ограничений RC 0.1.0.

## What Changes

- Завершить непроверенные части `design-task-console-architecture` tasks 5.2–5.4 и `define-task-jtbd` task 2.3: restricted-role task/chat/time/checklist/participants, navigation и completeness history/time.
- Независимо проверить membership прикреплённого файла и reassignment второму пользователю, сохранив различие между ACK и readback.
- Зафиксировать native REST3 populated auditors limitation отдельно от заранее выбранной композиции чтений task:show.
- Сохранить sanitized evidence с версией API, ролью, целями, expected/observed outcome и cleanup; не объявлять перенос успешным выполнением проверок.
- Сохранить действующие binding/policy/partial/outcomeUnknown/cancellation контракты и 29 post-MVP исключений. Возврат исключённых функций или новые API routes в change не входят.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

Нет. `skip_specs: true`: проверяются уже существующие контракты `task-mvp-scope`, `task-workflows` и `design-task-console-architecture/specs/task-console-runtime/spec.md`; поведение команд не меняется.

## Impact

Live integration tests, согласованные portal fixtures и verification evidence. Выполненные 30 happy-path и их sanitized records остаются доказательством RC 0.1.0 для проверенной роли/портала. Выявленные runtime-дефекты оформляются отдельным изменением с собственным scope; сам этот план не утверждает, что ограничения API устранимы.

## GitHub issues

- [#42 — Завершить portal-проверки прав и полноты](https://github.com/mesilov/bitrix24-cli/issues/42): весь change, milestone 0.2.0, стандартный label documentation. GitHub возвращает issueType=null; отдельный тип не подтверждён.
- [#10 — Поверхность команд задач](https://github.com/mesilov/bitrix24-cli/issues/10): источник перенесённых проверок; выполненный объём остаётся в 0.1.0.

## RC 0.1.0

Перенесённые проверки не блокируют RC 0.1.0. Недоказанные denied-role matrix, history/elapsedTime completeness, file membership и передача задачи второму пользователю остаются явно ограниченными. [Полный список границ RC](../../../docs/rc-0.1.0.md).
