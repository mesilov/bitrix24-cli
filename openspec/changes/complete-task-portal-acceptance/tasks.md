# Tasks

Milestone: **0.2.0**, issue [#42](https://github.com/mesilov/bitrix24-cli/issues/42). Ни одна новая live проверка не выполнена переносом scope. Исходные records и 30 happy-path сохраняются.

## 1. Роли и task/chat — остаток исходной 5.2

- [ ] 1.1 Подготовить согласованные allowed/restricted-role providers и fixtures; подтвердить пользователя/scopes и записать версию API, цели и expected/observed outcomes без secrets.
- [ ] 1.2 Дополнить card/schema, bounded list/title search и task/chat/message association cases; проверить разрешённые и запрещённые message update/delete в соответствующих per-command tests, записать sanitized evidence и cleanup.

## 2. Time/checklist — остаток исходной 5.3

- [ ] 2.1 Дополнить четыре REST1 time routes: собственные записи, принадлежность выбранной задаче и отказ прав; сохранить per-command assertions и cleanup отдельно от прежних happy-path.
- [ ] 2.2 Дополнить восемь checklist/root/item routes: wrong-task/rights rejection, PARENT_ID, root/nested tree, complete/renew без смены task status; сохранить результаты и cleanup.

## 3. Participants/history и границы данных — остаток исходной 5.4 и 2.3

- [ ] 3.1 Дополнить participants omitted/cleared/nonempty и denied-role cases; подтвердить strict-rest3 zero-call rejection всех non-v3 планов и сохранить evidence.
- [ ] 3.2 Проверить history/time navigation и completeness на известном наборе записей; сохранить доказанную полноту либо точное API ограничение с complete=false/partial/exit3.
- [ ] 3.3 Независимо проверить membership прикреплённого файла и reassignment второму пользователю; сохранить readback/cleanup либо документированное ограничение, не засчитывая ACK или назначение текущему пользователю.
- [ ] 3.4 Отдельно зафиксировать native REST3 populated auditors и заранее выбранные companion reads task:show; подтвердить сохранение task:list limitation, no fallback/retry и прежнего API scope.

## 4. Проверка evidence и трассировки

- [ ] 4.1 Выполнить targeted integration/unit checks для новых cases, проверить полноту role/API/target/outcome/call/cleanup records и отсутствие secrets; обновить verification и границы RC без заявления универсального portal proof.
- [ ] 4.2 Выполнить strict OpenSpec validation и verify; проверить обе стороны issue #42 ↔ change, сохранить перенесённые исходные IDs и фактические ограничения/пропуски.

## Workflow follow-up

- Архивировать после фактической verification, обновить архивный путь в #42; runtime-дефекты оформлять отдельными issues/changes.
