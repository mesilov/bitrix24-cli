# Design

## Context

См. proposal.md. На `dev d145b22` нет CHANGELOG.md; README ссылается на ранний CI artifact и содержит migration notes профилей/флага группы. Два active changes полностью завершены. `define-task-jtbd` сохраняет 2 незавершённые post-MVP задачи; остальные четыре active changes относятся к будущей работе.

## Goals / Non-Goals

**Goals:** завершить release bookkeeping и дать читаемую документацию фактической первой сборки с проверяемыми source/CI refs.

**Non-Goals:** менять runtime, API fields, зависимости, pipeline, версионный контракт или отмечать будущую приёмку выполненной. RC в `dev` остаётся development build; tag/Release являются отдельными действиями.

## Decisions

1. Аудитировать все 11 issues исходного milestone и архивы в `dev`. GitHub CLOSED сам по себе не доказывает поставку; evidence и связанные commits проверяются отдельно.
2. Архивировать только changes с завершёнными задачами и проверенной реализацией. Новые capabilities ci-quality-checks/task-console-runtime синхронизировать; существующий task-mvp-scope сохранить с уже принятыми уточнениями. Переписать относительные ссылки после перемещения, включая ссылки из активного define-task-jtbd. Архивные исторические records других changes не редактировать.
3. CHANGELOG.md описывает Added и ограничения 0.1.0 RC; история предварительных переименований/миграций не нужна пользователю первой сборки. README описывает profile selectors, runtime JSON и groupId в настоящем времени. Указание поддержанных платформ и ограничений API сохраняется.
4. Правило README до 1.0.0 закрепить в AGENTS.md по явному запросу пользователя. API compatibility/policy и ограничение поддержанных ОС не относятся к обратной совместимости продукта.
5. Проверить локальные Markdown targets/anchors, inventory, strict OpenSpec, make check/unit/lint-all. Native Linux/macOS/Windows и комплектность архивов подтвердить на final PR/source SHA в CI, затем на merge SHA dev. В README использовать проверенную подходящую сборку и рядом ссылку на текущие dev runs; source SHA каждого artifact остаётся явным.

## Risks / Trade-offs

- Перемещение change нарушит относительные ссылки → проверить все текущие docs/specs/active changes и moved files после archive.
- Существующий main spec содержит более поздние requirements → sync выполнять по requirement blocks, не перезаписывать весь capability устаревшим delta.
- Зелёный CI не повторяет browser/quarantine и portal acceptance → сохранить конкретные исходные evidence и ограничения #41/#42; новую CI matrix обозначать отдельно.
- Долгая native packaging → следить за фактическими jobs и не объявлять незавершённый run успешным.

## Delivery

Поставить PR в `dev`, дождаться применимых checks/native matrix, merge и проверок merge SHA. Архивировать этот docs-only change после завершения локальной реализации/verify; workflow follow-up хранить отдельными незачётными пунктами. Откат — отдельный revert PR.
