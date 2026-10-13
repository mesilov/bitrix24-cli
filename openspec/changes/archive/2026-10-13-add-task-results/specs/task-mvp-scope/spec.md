# Spec Delta

## ADDED Requirements

### Requirement: Task results admitted after the first release
Расширение 0.2.0 SHALL включать `task:result:add/list/update/delete` через REST3 по capability task-results. Исходные 30 команд и 29 исключений MVP 0.1.0 SHALL сохраняться как историческая граница первого релиза; допуск четырёх результатов SHALL не включать result:from-message, шаблоны, lifecycle или события приёмки.

#### Scenario: Task result commands in the development catalog
- **WHEN** формируется каталог после реализации #61
- **THEN** доступны 34 task-команды, включая четыре result-команды, и ни одна из них не выполняет скрытый lifecycle переход или результат из сообщения
