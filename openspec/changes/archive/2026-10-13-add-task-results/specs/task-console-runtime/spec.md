# Spec Delta

## MODIFIED Requirements

### Requirement: Exact MVP command registration
Runtime SHALL предоставлять 30 исходных task-команд MVP и четыре result-команды из согласованного расширения 0.2.0, отдельно от connection и встроенных help/completion. Регистрация SHALL строиться из явного CommandCatalog без tags/compiler pass и массового сканирования. Unit-tests SHALL проверять уникальность имён/классов, совпадение AsCommand с каталогом и отсутствие остальных исключённых операций и aliases.

#### Scenario: Root command inventory
- **WHEN** пользователь запускает b24cli без имени команды либо list task без профиля подключения
- **THEN** видны 34 task-команды, включая result:add/list/update/delete; остальные исключённые операции, включая result:from-message и subtask:add, отсутствуют; API и чтение credentials не выполняются

#### Scenario: Explicit catalog consistency
- **WHEN** запускаются unit-tests регистрации приложения
- **THEN** проверяются 34 имени и уникальных Command-класса, совпадение AsCommand и отсутствие aliases; compiler pass и console.command tags не требуются, credentials и API не читаются
