# task-console-runtime Delta

## MODIFIED Requirements

### Requirement: Exact MVP command registration
Runtime SHALL предоставлять 51 task-команду (43 базовые, пять команд шаблонов, task:handover, task:report и task:legacy:comment:list) и четыре users-команды user-directory, отдельно от локальных connection-команд connection-configuration и встроенной справки/completion. Регистрация SHALL строиться напрямую из единственного явного CommandCatalog без tags/compiler pass и массового сканирования. Исключённые операции не SHALL возвращаться через aliases.

#### Scenario: Root command inventory
- **WHEN** пользователь запускает b24cli без имени команды либо list task без профиля подключения
- **THEN** видна 51 согласованная task-команда, включая result:add/list/update/delete, семь lifecycle команд, task:my, task:status:durations, пять task:template:*, task:handover, task:report и task:legacy:comment:list; остальные исключённые команды отсутствуют, включая task:subtask:add и команды записи прежних комментариев; API и чтение credentials не выполняются

#### Scenario: Explicit catalog consistency
- **WHEN** запускаются unit-tests регистрации приложения
- **THEN** проверяются все 51 task-имя и четыре users-имени и уникальных Command-классов, совпадение AsCommand и отсутствие aliases; compiler pass и console.command tags не требуются, credentials и API не читаются

#### Scenario: Users have their own namespace
- **WHEN** вызывается list users
- **THEN** видны только users:current/find/colleagues/manager; list task сохраняет свою 51 команду, а local connection-команды остаются отдельными

#### Scenario: Lifecycle command integration coverage
- **WHEN** проверяются каталог и tests/Integration/Task
- **THEN** каждая из семи lifecycle команд имеет собственный CommandTest с group command:<имя> и happy-path; общий класс всех переходов не заменяет эти cases

## ADDED Requirements

### Requirement: Task chat history declares unchecked legacy comments
`task:chat:list` SHALL сообщать в metadata, что прежняя лента комментариев задачи не читалась, и указывать `task:legacy:comment:list` для её проверки. Полнота чата SHALL описывать только чат. Признак SHALL не добавлять API-запросов и не менять элементы, полноту и код выхода чтения чата.

#### Scenario: Complete chat after migration
- **WHEN** `task:chat:list TASK_ID --all --json` полностью прочитал новый чат с двумя служебными сообщениями
- **THEN** `meta.complete = true` относится к чату, а `meta.legacyComments` равен `not-checked` и содержит подсказку `task:legacy:comment:list TASK_ID`

#### Scenario: Task without migration
- **WHEN** `task:chat:list` читает чат задачи без прежней ленты
- **THEN** список сообщений, полнота, маршруты и код выхода совпадают с поведением без признака; добавляется только `meta.legacyComments`

#### Scenario: Human output
- **WHEN** `task:chat:list` выводит результат в человекочитаемом формате
- **THEN** после сообщений выводится одна строка о том, что прежние комментарии задачи не проверялись, с командой `task:legacy:comment:list TASK_ID`; в `--plain` эта строка не выводится в stdout
