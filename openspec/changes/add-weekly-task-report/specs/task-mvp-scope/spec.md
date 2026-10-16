# task-mvp-scope Delta

## ADDED Requirements

### Requirement: Weekly report admitted after the historical MVP
В milestone 0.3.0 CLI SHALL допускать read-only `task:report` по capability task-weekly-report через объявленный `tasks.task.list` REST1. Исторический список исключений 0.1.0 SHALL сохраняться; этот допуск MUST не включать остальные планируемые сводки, произвольные legacy методы или записи. Strict-rest3 SHALL отклонять отчёт до credentials/API.

#### Scenario: Narrow weekly report admission
- **WHEN** после реализации task-weekly-report формируется каталог 0.3.0
- **THEN** task:report добавляется как одна read-only команда; task:brief, task:risks и остальные недоставленные операции сохраняют свой статус планирования
- **AND** исходные исторические количества MVP не выдаются за текущий runtime-каталог

#### Scenario: Planning is distinct from runtime admission
- **WHEN** подготовлены только proposal, specs, design, tasks и JTBD-документация
- **THEN** task:report остаётся «План», CommandCatalog и число доступных команд не меняются, реализация и live-приёмка не считаются выполненными
