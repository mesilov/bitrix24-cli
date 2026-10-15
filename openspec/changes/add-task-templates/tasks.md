# Tasks

## 1. Contract

- [x] 1.1 Обновить согласованные proposal/design/spec/tasks со ссылками MCP, milestone 0.3.0 и границами проверки; проверить openspec validate add-task-templates --strict.

## 2. Runtime and command contract

- [x] 2.1 Реализовать port/REST1 adapter и пять команд с точным вводом, scalar response validation и JSON/plain; подтвердить TaskTemplatesTest для payload, типов, отрицательных ответов, файлов/stdin и sparse update.
- [x] 2.2 Подключить catalog/DI/scope/policy, offline add/update dry-run и read-only delete dry-run, force и неоднозначное удаление; подтвердить unit-тесты guard, scope и no-write/no-retry.
- [x] 2.3 Добавить пять отдельных portal integration classes и PM-08 JTBD, руководство, CLI map и changelog; проверить IntegrationCommandCoverageTest и cli-reference --check. Live tests запускать при наличии разрешённого подключения, иначе зафиксировать skip.

## 3. Verification and delivery

- [x] 3.1 Выполнить unit suite, quality linters, strict OpenSpec и verify requirement/scenario mapping; записать фактические результаты и ограничения.
- [ ] 3.2 Проверить issue↔change, интегрировать свежую origin/dev, повторить затронутые проверки, опубликовать ветку и открыть MR в dev с changelog и CI evidence.

## Workflow follow-up

- После разрешённого merge в dev и закрытия #72 синхронизировать specs, архивировать change, доставить архив отдельным MR и удалить worktree после сохранения результатов.
