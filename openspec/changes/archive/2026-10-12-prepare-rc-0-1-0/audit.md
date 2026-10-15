# Аудит RC 0.1.0 — 2026-10-12

Источник: `dev d145b226fd048423c2ce2af93b28efd733d4ccec`; [milestone 0.1.0](https://github.com/mesilov/bitrix24-cli/milestone/1). Все **11 исходных issues CLOSED** на момент проверки GitHub. Milestone counters включают также PR и не равны числу issues. Новая задача этой подготовки — [#47](https://github.com/mesilov/bitrix24-cli/issues/47).

## Проверенная поставка

| Issue | Реализация/evidence в dev | Итог |
| --- | --- | --- |
| [#2](https://github.com/mesilov/bitrix24-cli/issues/2) | Compose wrapper/Make, worktree-isolation suite; archive 2026-10-08-prepare-worktree-development, 13/13 | Реализовано, CLOSED |
| [#4](https://github.com/mesilov/bitrix24-cli/issues/4) | 4 workflows/configs/locked tools; archive 2026-10-12-add-ci-linters, 10/10; ci-quality-checks synced | Реализовано в retained scope, CLOSED; fork follow-up #41 |
| [#6](https://github.com/mesilov/bitrix24-cli/issues/6) | Alpine Dockerfile/runtime; archive 2026-10-08-slim-alpine-docker-image | Реализовано, CLOSED |
| [#7](https://github.com/mesilov/bitrix24-cli/issues/7) | 4 CI badges README; archive 2026-10-08-add-readme-ci-badges | Реализовано, CLOSED |
| [#10](https://github.com/mesilov/bitrix24-cli/issues/10) | Research archive 2026-10-08; 30 task commands/DI/adapters/per-command suites; archive 2026-10-12-design-task-console-architecture, 42/42; task-console-runtime/task-mvp-scope synced | Реализовано в retained scope, CLOSED; portal follow-up #42 |
| [#14](https://github.com/mesilov/bitrix24-cli/issues/14) | build scripts/3-platform CI/archives/notice and release gates; archive 2026-10-11-add-standalone-binary-build | Реализовано, CLOSED; tag/Release отдельно |
| [#18](https://github.com/mesilov/bitrix24-cli/issues/18) | README downloads/catalog; archive 2026-10-11-streamline-readme | Реализовано, CLOSED; актуализация текущей сборки в #47 |
| [#19](https://github.com/mesilov/bitrix24-cli/issues/19) | ConnectionManager/profile store/ACL/default.task/schema/native acceptance; archive 2026-10-12-add-cross-platform-connection-profiles, 20/20; PR #46 в dev d145b22 | Реализовано, CLOSED; архив и main spec проверены |
| [#23](https://github.com/mesilov/bitrix24-cli/issues/23) | Final macOS signature/payload/browser-quarantine evidence; archive 2026-10-12-fix-macos-first-launch, 7/7 | Реализовано, CLOSED; Developer ID/notarization не заявлены |
| [#24](https://github.com/mesilov/bitrix24-cli/issues/24) | README/build installation/PATH; archive 2026-10-11-clarify-standalone-path-installation | Реализовано, CLOSED |
| [#27](https://github.com/mesilov/bitrix24-cli/issues/27) | group-id declarations/mapper/search and tests; archive 2026-10-11-unify-group-id-option, 8/8 | Реализовано, CLOSED; ссылка на архив #19 актуализируется |

Runtime inventory проверен по `src/Bootstrap/CommandCatalog.php`: **30 task + 5 connection** commands. Current source and production PHP files не изменены этой подготовкой. Общие локальные проверки: **366 tests / 2104 assertions**, make check/unit/lint-all PASS. CI/package source refs записываются в issue #47/PR после завершения конкретных runs.

## Активная будущая работа

- define-task-jtbd: **8/10**; открытые 2.1/2.2 — проектирование полного PM/AM покрытия после MVP. Архивировать незавершённый change нельзя.
- improve-task-cli-dx: **0/16**, #12/0.2.0.
- add-user-feedback-loop: **0/18**, #15/0.2.0.
- verify-external-fork-ci: **0/4**, #41/0.2.0.
- complete-task-portal-acceptance: **0/10**, #42/0.2.0.

Ни одна future task не отмечена выполненной. Portal record 11.10.2026 сохранён без пересчёта: **42 tests / 395 assertions / 41 passed / 1 restricted-role skip**. Новые portal/fork проверки в #47 не выполнялись. [Все границы RC](../../../../docs/rc-0.1.0.md).
