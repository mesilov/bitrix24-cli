# Spec Delta

## Purpose

Определить проверяемый расчёт календарного времени задачи в статусах по истории переходов, включая повторные открытия и явные границы достоверности неполных данных.

## ADDED Requirements

### Requirement: Read-only status duration command
CLI SHALL предоставлять `task:status:durations TASK_ID [--as-of ISO8601]` как read-only операцию с общими connection/API policy/output контрактами. TASK_ID SHALL быть положительным; asOf SHALL содержать timezone и не быть в будущем. Ошибки ввода SHALL отклоняться до credentials/API. REST1 история SHALL отклоняться strict-rest3 до подключения.

#### Scenario: Invalid input or strict policy
- **WHEN** TASK_ID невалиден, asOf без timezone/в будущем либо включён strict-rest3
- **THEN** возвращается существующий usage-error/exit2 либо policy-denied/exit4 до credentials и запросов, без мутаций

### Requirement: Canonical status transition evidence
Расчёт SHALL использовать фактические переходы статуса, сохраняя исходные field/from/to/createdDate/userId/eventId и их provenance. REAL_STATUS SHALL быть основным источником. STATUS SHALL использоваться только при подтверждённой эквивалентности; мета-статусы и стадии не SHALL смешиваться с жизненным циклом. Неизвестные значения SHALL оставаться явно неизвестными.

#### Scenario: Status events have different meanings
- **WHEN** история содержит REAL_STATUS, STATUS, STAGE_ID либо неизвестное значение
- **THEN** дублирующий STATUS не создаёт второго перехода, стадии исключены, неподтверждённое соответствие не превращается в точный статус/длительность

### Requirement: Chronological and consistent history
CLI SHALL нормализовать даты к абсолютному времени с сохранением исходных offsets, дедуплицировать одинаковые события по ID и проверять цепочку from/to. Равные timestamps SHALL иметь порядок, доказанный контрактом или единственной согласованной цепочкой; ID сам по себе не доказывает хронологию. Конфликтующий ID, плохая дата и разрыв SHALL делать расчёт partial.

#### Scenario: Duplicates and conflicting sequence
- **WHEN** события повторены, имеют одинаковое время, конфликтующий ID или несовместимую цепочку
- **THEN** идентичные дубли учитываются один раз; неоднозначность/конфликт отражены в reasons и не дают точных итогов

### Requirement: Repeated entries are separate intervals
CLI SHALL сохранять отдельный интервал каждого входа в статус и суммировать все подтверждённые интервалы по статусу. Завершение не SHALL обрывать историю; время completed до повторного открытия/asOf SHALL учитываться как календарное время. from=to не SHALL создавать новый вход.

#### Scenario: Task is reopened twice
- **WHEN** полная история содержит pending → in_progress → completed → pending → in_progress → completed → pending → completed
- **THEN** каждый вход сохранён, интервалы повторных статусов суммированы, первое завершение не обрывает расчёт

### Requirement: Initial state has an explicit boundary
Начальный интервал SHALL начинаться только с доказанной даты создания и доказанного начального статуса при полной непротиворечивой истории. Первое наблюдаемое событие не SHALL заменять создание. Текущий статус не SHALL подставляться вместо начального; отсутствие доказательства SHALL оставлять unknown boundary.

#### Scenario: History starts after task creation
- **WHEN** NEW/дата создания или начальный статус не доказаны
- **THEN** период перед первым доказанным входом имеет неизвестную границу, итог за жизнь задачи не объявляется полным

### Requirement: Single reproducible as-of boundary
CLI SHALL зафиксировать default asOf до сетевых чтений. Расчёт SHALL использовать полуоткрытые интервалы [enteredAt,leftAt), обрезанные asOf, и секунды абсолютного времени. События после asOf SHALL служить проверке цепочки, не начисляя время. asOf ранее доказанного создания SHALL отклоняться как usage-error после чтения данных.

#### Scenario: Historical report after a later reopening
- **WHEN** asOf предшествует повторному открытию, а задача сейчас в другом статусе
- **THEN** расчёт использует состояние на asOf из истории, не сегодняшний статус, и не начисляет время после asOf

### Requirement: Tail and concurrent change validation
Последний интервал до asOf SHALL считаться точным только при доказанной непрерывности истории до этой границы. Изменение состояния во время обхода или расхождение последнего перехода с доступным подтверждением состояния SHALL давать partial. Чтения не SHALL представляться атомарным snapshot.

#### Scenario: Task changes during collection
- **WHEN** контрольные чтения/история указывают на конкурентное изменение или не подтверждают последний статус
- **THEN** точный хвост и итог не выдаются, вывод содержит соответствующую причину неполноты

### Requirement: Incomplete history never yields exact totals
Неполная история, ошибка страницы, исчерпание бюджета, недоказанная navigation или противоречие SHALL приводить к complete=false, reasons и exit3. Наблюдаемые переходы SHALL сохраняться, но exact totals SHALL быть null. Согласованные соседние события не SHALL доказывать отсутствие пропущенных циклов; промежутки не SHALL называться гарантированной нижней границей времени в статусе.

#### Scenario: A hidden reopen cycle fits between observed events
- **WHEN** источник неполон, хотя from/to соседних полученных переходов согласованы
- **THEN** рассчитанное распределение времени имеет verified=false, точные суммы null; потенциальный пропущенный цикл не игнорируется

#### Scenario: API fails after a first page
- **WHEN** часть событий получена, затем API возвращает ошибку или отсутствует подтверждённая навигация
- **THEN** события и исходная причина сохранены, complete=false/exit3; исходная ошибка до любых данных сохраняет существующий error contract

### Requirement: Traceable intervals and status totals
JSON SHALL содержать asOf, интервалы enteredAt/leftAt/status/seconds/verified/evidenceIds и totals по статусам. Для неизвестных границ/распределения seconds SHALL быть null; разница наблюдаемых timestamps SHALL выводиться отдельно как elapsedBetweenEventsSeconds, без обещания времени в статусе. Human/plain SHALL показывать неизвестность и причины, а не ноль.

#### Scenario: Known boundaries but unverified occupancy
- **WHEN** две временные метки известны, но полнота между ними не доказана
- **THEN** elapsedBetweenEventsSeconds содержит разницу, seconds=null и verified=false; суммарное время по статусу не подменяется этой разницей

### Requirement: Calendar time is distinct from effort
Расчёт SHALL учитывать календарные секунды, включая ночи, выходные и время в completed. CLI SHALL явно обозначать эту семантику; данные трудозатрат не SHALL смешиваться с длительностями. Точные totals при полном диапазоне SHALL покрывать создание → asOf без пропусков/двойного начисления.

#### Scenario: Calendar period exceeds tracked effort
- **WHEN** задача находится в статусе сутки, а учёт времени содержит один час
- **THEN** календарная длительность составляет 86400 секунд независимо от трудозатрат и рабочих часов

### Requirement: Independent evidence and documentation
Команда SHALL иметь отдельные offline проверки и собственный integration test с SDK fixtures/readback/cleanup. Live evidence SHALL проверять повторное открытие и фактические status timestamps; документированный partial-result SHALL проверяться отдельно и не доказывать точные totals. PM-02/AM-03 SHALL трассироваться на команду и файл её теста в JTBD документации.

#### Scenario: Portal cannot prove complete history
- **WHEN** live статусные события доступны, но navigation не доказывает полноту
- **THEN** partial boundary проверяется с точной причиной, а acceptance точных totals остаётся непроверенным; fixtures/unit pass не объявляются live proof

#### Scenario: Rejected review followed by successful completion
- **WHEN** disposable SDK fixture passes through pending → in_progress → supposedly_completed → pending after disapprove, then in_progress → supposedly_completed → completed after approve
- **THEN** live evidence retains independent state readbacks and newly observed raw history events after each action, distinguishes pre-existing events by ID, and records cleanup; this does not upgrade history completeness or prove exact totals
