# Spec Delta

## MODIFIED Requirements

### Requirement: API admission and future lifecycle acceptance remain explicit
Каталог SHALL отделять release scope от API support: 59 операций = 30 кандидатов MVP, 0 API-policy-pending и 29 после MVP. Через REST1 явно допущены 4 time, 8 checklist/root/item, 2 participants/history команды и выбранные companion reads участников task:show. Права SHALL проверяться для кандидатов; lifecycle SHALL оставаться после MVP независимо от возможности записи status. Наличие команды в каталоге не SHALL означать принятие иных API exceptions, доказанную семантику или runtime readiness.

#### Scenario: Legacy admission is limited to selected commands
- **WHEN** вызывается legacy маршрут вне согласованных команд/companion reads либо общий tasks.task.update используется для действий за пределами participants:set
- **THEN** допуск выбранных REST1 команд и чтений не разрешает такую операцию; policy отклоняет запрос и не выполняет скрытый fallback или исключённый lifecycle переход

#### Scenario: Lifecycle commands after MVP
- **WHEN** формируется MVP или пользователь передаёт смену status через task:update --fields/--fields-file
- **THEN** все семь lifecycle команд отсутствуют в MVP; raw смена status отклоняется до записи и не предоставляется через другое имя или неявный шаг; чтение status и обычное изменение полей сохраняются

#### Scenario: Future lifecycle admission preserves required result
- **WHEN** после MVP проектируется допуск lifecycle команд
- **THEN** до допуска проверяются needsControl, requireResult, права и side effects; при отсутствующем или непроверенном допустимом native result task:complete не выполняет переход, не создаёт result неявно, не подменяет его файлом/чатом и не отключает requireResult; сокращение scope не считается acceptance

#### Scenario: Explicit user enrichment of selected task participants
- **WHEN** task:show получает select auditors/accomplices в default task-v3 режиме
- **THEN** допустимы только REST3 карточка без этих relations, REST1 tasks.task.get для ID выбранных ролей и user.get для нужных полей выбранных пользователей; версии отражаются в meta, strict-rest3 отклоняет план до credentials/API, недоступные данные дают partial вместо пустого подтверждённого списка

## Решение о приёмке RC 0.1.0 — 12.10.2026

По явному решению пользователя непроверенные части portal tasks 5.2–5.4 и связанного `define-task-jtbd` task 2.3 перенесены в milestone **0.2.0**, [#42](https://github.com/mesilov/bitrix24-cli/issues/42), change `complete-task-portal-acceptance` (`openspec/changes/complete-task-portal-acceptance/`). Они исключены из обязательной приёмки RC 0.1.0; перенос не является новым runtime proof. Уже выполненные 30 собственных happy-path и исходные records сохраняются.

Границы RC: denied-role matrix не подтверждена; history/elapsedTime completeness частична; file attachment ACK без независимого membership; reassignment только на текущего пользователя; native REST3 filled auditors limitation сохраняется для task:list, task:show использует заранее выбранную композицию чтений без error-triggered fallback/retry. Binding/policy/partial/outcomeUnknown/cancellation и 29 post-MVP исключений сохраняются. #19/#23/native packaging остаются критериями RC. [Полный список ограничений](../../../../../docs/rc-0.1.0.md).
