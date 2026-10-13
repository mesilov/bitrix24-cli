## Purpose

Описывает read-only персональную выборку доступных задач заданного исполнителя для EMP-01 с открытыми состояниями, сроком и явными границами полноты.

## ADDED Requirements

### Requirement: Explicit executor selection
`task:my` SHALL требовать положительный `--user ID` и показывать только задачи этого исполнителя в пределах прав подключения. ID SHALL не подменять caller и не запускать неявный поиск текущего пользователя.

#### Scenario: Selected executor differs from caller
- **WHEN** задан валидный --user ID другого исполнителя
- **THEN** выбираются только его доступные caller задачи; выбор ID не расширяет права

#### Scenario: Missing or invalid user
- **WHEN** --user отсутствует либо не является положительным целым ID
- **THEN** usage-error/exit 2 возникает до credentials/API

### Requirement: Open task predicate
`--open` SHALL включать pending, in_progress, supposedly_completed и deferred и исключать completed/declined согласно существующей нормализации состояний. Без флага SHALL сохраняться все состояния выбранного исполнителя.

#### Scenario: Open and terminal states
- **WHEN** у исполнителя есть задачи в открытых и терминальных состояниях
- **THEN** --open возвращает только четыре открытых состояния; без флага состояния не отфильтровываются

### Requirement: Deadline and predicate conjunction
`--due-before` SHALL принимать ISO8601 с часовым поясом; executor/open/deadline SHALL объединяться через AND. Deadline SHALL быть строго раньше границы; задачи без deadline не SHALL проходить фильтр срока.

#### Scenario: Boundary and missing deadline
- **WHEN** задан срок, а задачи имеют дату до/на/после границы либо null
- **THEN** проходят только даты строго раньше границы; timezone учитывается при сравнении моментов

#### Scenario: Combined selection
- **WHEN** --user, --open и --due-before заданы вместе
- **THEN** возвращаются только задачи выбранного исполнителя в открытом состоянии со сроком до границы

#### Scenario: Invalid date
- **WHEN** --due-before не соответствует ISO8601 с часовым поясом
- **THEN** usage-error/exit 2 возникает до credentials/API

### Requirement: Bounded scan and honest completeness
`--max-scan` SHALL быть положительным бюджетом с default 10000; порядок id DESC и default output limit 50 SHALL соответствовать task:list. Исчерпание обхода или отсутствие/неизвестность обязательных predicate fields SHALL давать partial/exit 3, meta.complete=false и причину; совпадения SHALL не выдаваться за полный набор.

#### Scenario: Budget exhausted
- **WHEN** scan budget исчерпан до завершения доступной выборки
- **THEN** возвращаются накопленные совпадения с scanned/reason и partial/exit 3

#### Scenario: Missing predicate data
- **WHEN** для проверки executor/open/deadline не хватает поля или открытость состояния неизвестна
- **THEN** полнота не утверждается и причина явно отражена; неизвестные данные не считаются успешным совпадением

#### Scenario: Invalid budget
- **WHEN** --max-scan не является положительным целым
- **THEN** возвращается usage-error/exit 2 до credentials/API

### Requirement: Consistent output and existing list behavior
Команда SHALL сохранять human/JSON/plain контракты task:list, общие profile/API-policy options и офлайн справку. Добавление task:my SHALL не менять результаты существующих task:list/task:find. Наличие команды SHALL не объявлять автоматическую приоритизацию или сквозную полноту EMP-01.

#### Scenario: Offline discovery
- **WHEN** вызываются --help, list task или completion без подключения
- **THEN** task:my и её опции доступны без credentials/API

#### Scenario: Read-only and API policy
- **WHEN** task:my выполняется с корректным вводом и strict-rest3
- **THEN** выполняются только чтения текущей REST3 выборки; ошибки и partial сохраняют общий контракт, скрытого fallback или записи нет

#### Scenario: Existing list regression
- **WHEN** повторены существующие list/find сценарии на тех же fixtures
- **THEN** их фильтры, порядок, budget и выходные данные сохраняются
