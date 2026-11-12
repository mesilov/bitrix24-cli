# Tasks

## 1. Shared calendar and extended search windows

- [x] 1.1 Выделить общий календарь (now/timezone, bounds, day tokens) из DeadlineWindowResolver без изменения поведения 7 существующих окон; проверить, что DeadlineWindowTest, TaskDeadlineFilterTest и TaskDateWindowFilterTest проходят без правок ожиданий.
- [x] 1.2 Добавить last-week, next-week, last-month, next-month, last-quarter, next-quarter, next-7-days, last-7-days в таблицу окон; проверить frozen-clock тестами все сценарии spec task-deadline-windows (2026-11-13, переход года 2027-01-05, Europe/Berlin DST 2026-10-25 для next-7-days, last-7-days, overdue + next-7-days).
- [x] 1.3 Обновить suggestedValues/completion и help task:list/task:find новыми значениями из общей константы; проверить вывод `--help` и completion-тест на полный список значений и порядок.

## 2. Actual completion window

- [x] 2.1 Добавить `--closed-window` (closed → CLOSED_DATE) в mapper, TaskQuery/TaskListRoute и обе команды; проверить unit-тестами payload REST1 `>=CLOSED_DATE`/`<CLOSED_DATE` с offset без DEADLINE/REAL_STATUS, AND с `--status completed`/группой/ролью, meta.closedFilter и strict-rest3 policy-denied/exit4 без сети.
- [x] 2.2 Реализовать nullable-семантику: null/'' CLOSED_DATE → несовпадение без incomplete, нераспознаваемое значение → missing-predicate-field/exit3; проверить тестами строк «никогда не завершалась», «возобновлена с сохранённой CLOSED_DATE» (с `--status completed` и без), max-scan/partial.
- [x] 2.3 Проверить usage-error/exit2 до API для неизвестного/повторного `--closed-window` и смешения с `--where`/`--where-file`/`--due-before`; обновить docs/task-usage.md разделом о фактическом завершении и отличии от дедлайна/статуса, проверить примеры командой `--dry-run`/unit fixture.

## 3. Relative deadline assignment

- [x] 3.1 Реализовать resolver дня и времени (today/tomorrow/day-after-tomorrow, monday…sunday строго после сегодня, next-week-ДЕНЬ, YYYY-MM-DD, HH:MM) с отказом для DST gap/overlap и срока ≤ now; проверить frozen-clock тестами все сценарии spec task-deadline-assignment, включая 2026-12-31 → 2027-01-01, 2028-02-29/2027-02-29 и Europe/Berlin 2026-10-25/2027-03-28.
- [x] 3.2 Добавить `--deadline-day`/`--deadline-time`/`--timezone` в task:add и task:update, `--day`/`--time`/`--timezone` в task:deadline:set; проверить unit-тестами REST3 payload deadline, legacy REST1 DEADLINE с `--user-fields`, sparse patch task:deadline:set и все взаимоисключения (day без time, time без day, с `--deadline`/`--at`/`--fields`, timezone без day) как usage-error/exit2 до credentials/API.
- [x] 3.3 Прокинуть meta.deadlineResolution в dry-run и результат записи (confirmed/partial/outcomeUnknown) без автоматического повтора; проверить command-тестами dry-run без mutating вызова и outcomeUnknown с сохранённым вычисленным значением.
- [x] 3.4 Обновить help/completion трёх команд и docs/task-usage.md примерами «завтра в 18:00» и «пятница в 17:00», явно указав отсутствие часа по умолчанию; проверить `--help` и примеры через `--dry-run`.

## 4. Documentation, skill and acceptance

- [x] 4.1 Обновить README (команды и флаги первой сборки), docs/task-jtbd.md (сценарии, статусы, команды, ссылки на тесты, дата/source SHA), docs/cli-command-map.md через `make cli-reference` и CHANGELOG Unreleased со ссылкой на #141; проверить сверкой с CommandCatalog и `git diff origin/dev...HEAD -- CHANGELOG.md`.
- [x] 4.2 Запустить `make unit` и `make lint-all`, `openspec validate add-relative-task-dates --strict`; зафиксировать результаты в verification.md отдельно от native и live проверок.
- [ ] 4.3 Native binary: собрать артефакт через builds/CI по запросу пользователя и проверить вне checkout `--help` новых флагов, `task:list --deadline-window next-7-days --dry-run`-эквивалент и `task:deadline:set --day ... --dry-run`; записать SHA сборки и ограничения в verification.md.
- [ ] 4.4 Live read-only проверка на портале: `--closed-window`, новые окна и группа; при отдельном разрешении пользователя на запись — renew тестовой задачи и переход в «ожидает контроля» для поведения CLOSED_DATE; сохранить безопасный receipt без секретов и отметить непроверенные части.
- [ ] 4.5 Обновить bitrix24-cli-skill отдельным MR после доступности флагов: mapping сценариев spec (сроки, периоды, завершение), уточнение времени без default и отказ от обхода при старой версии CLI; проверить трассировку skill MR ↔ #141.

## Workflow follow-up

- Перед MR: свежий `git fetch origin` и `git merge --no-edit origin/dev`, повтор затронутых проверок, проверка трассировки #141 ↔ change, milestone 0.3.0, label enhancement и CHANGELOG.
- После подтверждённого merge в dev и закрытия #141: архивировать change, синхронизировать specs, обновить путь в issue, доставить архив MR в dev и удалить worktree по правилам maintainer.
