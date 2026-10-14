# Tasks

## 1. Scope and traceability

- [x] 1.1 Выделить шаблоны в отложенное issue/change, сузить #61 до результатов; проверить обе ссылки, enhancement и milestone 0.2.0 через GitHub.

## 2. Result runtime

- [x] 2.1 Реализовать REST3 gateway/adapter и Application service с binding, bounded list, строгим ACK и outcomeUnknown; проверить целевые unit cases payload/foreign IDs/malformed responses/partial.
- [x] 2.2 Добавить четыре команды, requests, mapper, DI/catalog, plain profile и README; проверить JSON/plain, validation, strict-rest3, text-file/stdin, dry-run и deletion gate.
- [x] 2.3 Добавить отдельные happy-path integration classes для каждой команды и binding cases; проверить coverage unit test и явно записать live execution либо skip без webhook.

## 3. JTBD

- [x] 3.1 Добавить PM-07/AM-07/EMP-08 в JTBD Markdown/JSON и runtime coverage 0.2.0; проверить соответствие delta spec и имен команд, сохранив исходные wider goals и MVP history.

## 4. Verification and delivery

- [x] 4.1 Выполнить make unit/check/lint-all, strict OpenSpec и git diff --check; сохранить verification с точными границами доказательств.
- [x] 4.2 Синхронизироваться с свежей origin/dev, повторить затронутые проверки и открыть PR в dev с #61 и обоими changes; проверить target/head и доступные CI результаты.

## Workflow follow-up

- После подтверждённого merge в dev и закрытия #61 архивировать add-task-results, синхронизировать specs, обновить путь в issue и доставить архив в dev; удалить worktree после сохранения результата.
- add-task-templates оставить активным, не выполнять его задачи до отдельного запроса пользователя.
