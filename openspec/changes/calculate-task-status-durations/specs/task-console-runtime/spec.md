# Spec Delta

## MODIFIED Requirements

### Requirement: Exact MVP command registration
Runtime SHALL предоставлять 43 task-команды (42 текущие, включая семь lifecycle команд и task:my, и task:status:durations) и четыре users-команды user-directory, отдельно от локальных connection-команд connection-configuration и встроенной справки/completion. Регистрация SHALL строиться напрямую из единственного явного CommandCatalog без tags/compiler pass и массового сканирования. Исключённые операции не SHALL возвращаться через aliases.

#### Scenario: Root command inventory
- **WHEN** пользователь запускает b24cli без имени команды либо list task без профиля подключения
- **THEN** видны все 43 согласованные task-команды, включая result:add/list/update/delete, семь lifecycle команд, task:my и task:status:durations; остальные исключённые команды отсутствуют, включая task:subtask:add; API и чтение credentials не выполняются

#### Scenario: Explicit catalog consistency
- **WHEN** запускаются unit-tests регистрации приложения
- **THEN** проверяются все 43 task-имени и четыре users-имени и уникальных Command-классов, совпадение AsCommand и отсутствие aliases; compiler pass и console.command tags не требуются, credentials и API не читаются

#### Scenario: Users have their own namespace
- **WHEN** вызывается list users
- **THEN** видны только users:current/find/colleagues/manager; list task сохраняет свои 43 команды, а local connection-команды остаются отдельными

#### Scenario: Lifecycle command integration coverage
- **WHEN** проверяются каталог и tests/Integration/Task
- **THEN** каждая из семи lifecycle команд имеет собственный CommandTest с group command:<имя> и happy-path; общий класс всех переходов не заменяет эти cases
