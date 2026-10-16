# Verification — task:handover (#162)

Дата: 16.10.2026. База: `6eb4673e16ca21fcd242c3f9b1b987dc82716752`. Ветка: `feature/162-task-handover`.

## Результат

| Измерение | Проверка |
| --- | --- |
| Полнота | 7/8 задач: реализация и проверки завершены; 5.2 (MR/review/CI/merge) ожидает доставки. |
| Корректность | 8 изменённых требований, 19 сценариев сопоставлены с реализацией и тестами. Проверка на реальном портале не выполнена. |
| Согласованность | CommandRunner, явный CommandCatalog/ROUTES, DI, policy guard, ScopeManifest и общий presenter сохранены. Main specs синхронизированы. |

Функциональных CRITICAL замечаний при локальной проверке нет. Незавершённая задача 5.2 остаётся препятствием для завершения доставки и архивирования; merge/CI здесь ещё не подтверждены. Живая приёмка новой команды не подтверждена и не заменяется наличием интеграционного класса.

## Требования и доказательства

| Требование | Реализация / проверка |
| --- | --- |
| Источник, получатель, проект; режим руководителя | `TaskInputMapper::handoverTasks`, `HandoverTasksCommand`, `TaskHandoverService::prepare`; invalid input, self/manager, offline discovery в `TaskHandoverCommandTest`. |
| Активные участники и политика | `Rest1ProjectMemberAdapter`, `assertMembers`, явный REST1 route; malformed/missing membership, recheck, strict-rest3 zero-call и scope isolation unit cases. |
| Полная выборка до записи | Переиспользован `TaskListScanner`, where source/group, openOnly, limit=null; 51 задача на двух страницах, budget/missing fields и empty selection проверены. |
| GET/UPDATE/GET и контекст | `execute` проверяет карточку, отправляет только responsibleId, проверяет ответственного и проект; unit cases сверяют остальные поля и readback mismatch. |
| Отчёт и остановка | confirmed/failed/unverified/unknown/skipped, причины пропуска, partial exit 3, отмена 130; lost response, malformed ACK, отказ, конфликт, отмена и no-retry проверены. |
| EMP-09 | Main task-workflows и docs/task-jtbd.md; ситуация/цель/успех, команда и dedicated integration link. |
| PM-04 | Существующий сценарий сохранён; добавлен режим `--from` для передачи между двумя сотрудниками. Эта проверка не объявляет весь прежний процесс согласования плана реализованным. |
| Каталог runtime | 49 task-команд, 63 публичных команды, уникальные классы, AsCommand, dedicated integration ownership, справка и карта проверены. |

## Выполненные проверки

- `make unit`: **876 tests / 15146 assertions**, PASS. Целевой набор handover: **15 tests / 837 assertions**, PASS.
- `make check`: Composer validation/platform requirements, PHP syntax и запуск CLI — PASS.
- `make lint-all`: licenses, PHP CS Fixer и PHPStan — PASS; единственная финальная правка Rector — пустая строка после блока, затем отдельно повторён `make lint-rector`.
- `make cli-reference-check`: PASS, **63 публичные команды**.
- `openspec validate define-vacation-task-handover --strict`: PASS; `openspec validate --specs --strict`: **23 main specs**, PASS.
- Статическая проверка **230 локальных Markdown-ссылок**, **24 уникальных JTBD ID (8 PM / 7 AM / 9 EMP)** и синтаксис Python package smoke — PASS.
- `python3 build/tests/compose-connection-check.py`: PASS (4 argument fixtures).
- `git diff --check`: PASS до добавления отчёта; staged diff повторно проверяется перед commit.
- `make integration TEST_ARGS='--group command:task:handover'`: **2 skipped**, без webhook. Тест требует второго test webhook и явного `B24CLI_TEST_HANDOVER_GROUP_ID` для изолированного проекта. Массовые изменения в реальном проекте не выполнялись.

## Границы

Проверка состава через официальный MCP подтверждает документированный REST1 `sonet_group.user.get`, scope sonet и активные роли A/E/K. Это не вызов портала. Для задач используются существующие REST3 adapters и точные поля. Не подтверждены на портале права обычного руководителя, доступ коллеги после переназначения и поведение на конкретном тарифе. Dedicated integration case готовит только собственные fixtures после проверки пустого исходного набора и dry-run; он не запускается на произвольном проекте автоматически.

Набор ограничен доступными подключению задачами и снимком до записи. API не даёт атомарного compare-and-set: изменение между последним GET и UPDATE остаётся возможным. Команда прекращает следующие операции при обнаруженном расхождении. При unverified/unknown нужно проверить task:show до повторного решения. Записи автоматически не повторяются и не откатываются.

Native package smoke обновлён под 49 task-команд; новый бинарник/релиз в этой работе не собирался и не публиковался. Исходный checkout и его незакоммичённые изменения сохранены.
