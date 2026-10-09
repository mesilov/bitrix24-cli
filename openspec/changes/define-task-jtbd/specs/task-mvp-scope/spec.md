# Spec Delta

## Purpose

Определить согласованную границу первого релиза блока задач b24cli: исключённые пользовательские операции, условия допуска оставшихся команд и отделение MVP от общих требований task-workflows. Решение пользователя от 2026-10-09 уточняет планирование, не подтверждает реализацию команд.

## ADDED Requirements

### Requirement: Explicit exclusions from the task MVP
CLI MVP SHALL исключать 16 команд зависимостей, рабочих представлений, контекста/препятствий и изменений/клиентских договорённостей, перечисленных в сценарии. Каталог SHALL сохранять их в области после MVP. Отсутствие требований к маппингу CLI/REST 1:1 не SHALL разрешать вернуть эти операции в MVP под другим именем или как неявный шаг.

#### Scenario: Excluded command groups
- **WHEN** формируются список команд, справка и критерии приёмки MVP
- **THEN** следующие команды относятся к развитию после MVP и не предоставляются в CLI первого релиза:

```text
task:dependency:list
task:dependency:add
task:dependency:delete
task:my
task:plan
task:brief
task:risks
task:report
task:context:set
task:blocker:raise
task:blocker:resolve
task:change:propose
task:change:accept
task:delivery:record
task:feedback:record
task:acceptance:record
```

### Requirement: API and lifecycle admission remains explicit
Каталог SHALL отделять release scope от API support: после 16 исключений остаются 39 команд в рассмотрении, 28 кандидатов MVP и 11 команд с неутверждённым допуском прежнего API. Lifecycle gates и права SHALL сохраняться для кандидатов. Наличие команды в остатке не SHALL означать принятие API exception, доказанную семантику или runtime readiness.

#### Scenario: Remaining legacy extension
- **WHEN** команда времени, чек-листа, участников или истории использует прежний API и решение о допуске ещё не принято
- **THEN** она остаётся в отдельном разделе API-policy-pending, не считается утверждённой частью MVP и не включается через скрытый fallback

#### Scenario: Remaining lifecycle candidate
- **WHEN** task:complete, task:approve или другая lifecycle команда сохраняется кандидатом MVP
- **THEN** до её допуска проверяются needsControl, requireResult, права и side effects; сокращение MVP не считается такой проверкой

### Requirement: MVP coverage is distinct from product JTBD
Матрица SHALL сохранять 19 согласованных JTBD и отдельно перечислять оставшиеся и исключённые связи команд для MVP. Сокращённый mapping не SHALL выдаваться за полное покрытие сценария; отсутствие mapped команды не SHALL удалять продуктовое требование. Ручные действия через базовые retained команды SHALL отличаться от исключённых сводок и автоматизации.

#### Scenario: Client promises and status reporting after MVP
- **WHEN** оцениваются AM-02 и AM-03 после исключения risks/report/context/brief
- **THEN** MVP mapping этих сценариев пуст; общие JTBD сохраняются для последующего развития и не входят в утверждение о полноте MVP

#### Scenario: Personal task selection through the base list
- **WHEN** сотрудник вызывает `b24cli task:list --responsible 42` в пределах своего доступа
- **THEN** используется оставшийся базовый список с явной фильтрацией и контролем scan completeness; исключённая task:my не запускается и не требуется для этого вызова
