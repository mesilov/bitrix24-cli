# Spec Delta

## Purpose

Позволить пользователю и skill выбирать задачи по однозначным календарным окнам дедлайна и просрочке через публичный CLI с серверными диапазонами дат.

## ADDED Requirements

### Requirement: Canonical calendar windows
task:list и task:find SHALL принимать --deadline-window today, tomorrow, yesterday, day-before-yesterday, this-week, this-month или this-quarter. Неделя MUST начинаться в понедельник, кварталы — в январе, апреле, июле и октябре. Скользящие длительности MUST NOT заменять календарные периоды.

#### Scenario: Day windows
- **WHEN** дата команды в выбранном timezone равна 2026-10-13
- **THEN** today, tomorrow, yesterday и day-before-yesterday задают соответственно полные дни 13, 14, 12 и 11 октября, от полуночи включительно до следующей полуночи исключительно

#### Scenario: Calendar depth
- **WHEN** команда запускается 2026-10-13 с this-week, this-month или this-quarter
- **THEN** окна равны соответственно [2026-10-12, 2026-10-19), [2026-10-01, 2026-11-01), [2026-10-01, 2027-01-01) в выбранном timezone

### Requirement: Deterministic time resolution
CLI SHALL фиксировать now один раз на команду и использовать его для всех страниц. --timezone SHALL принимать IANA timezone; без флага SHALL использоваться локальный timezone окружения CLI, явно отображённый в metadata. Границы MUST рассчитываться календарно с учётом DST и календаря, а не фиксированным количеством секунд.

#### Scenario: Timezone changes the current day
- **WHEN** один instant соответствует разным локальным датам в двух явно выбранных timezone
- **THEN** today соответствует локальному дню каждого выбранного timezone, а API получает datetime с явным offset

#### Scenario: Calendar boundaries and long scan
- **WHEN** выборка пересекает полночь или окно содержит DST, високосный февраль либо смену квартала/года
- **THEN** все страницы используют начальный now и исходное окно; календарные границы не дрейфуют

### Requirement: Overdue deadline semantics
--overdue SHALL выбирать задачи с непустым deadline строго меньше now, исключая завершённые и отклонённые; политика включает отложенные и ожидающие контроля. Сочетание с --deadline-window SHALL пересекать календарное окно с deadline<now. Без --overdue окно SHALL сохранять все статусы.

#### Scenario: Past due within the current week
- **WHEN** пользователь задаёт --overdue --deadline-window this-week
- **THEN** результат содержит только незавершённые, не отклонённые задачи с deadline от начала недели включительно до начального now исключительно

#### Scenario: No deadline and boundary equality
- **WHEN** deadline отсутствует/null, равен now либо задача завершена/отклонена
- **THEN** задача не совпадает с --overdue; null не совпадает и с календарным окном

#### Scenario: Deadline window without overdue
- **WHEN** завершённая задача имеет deadline вчера и запрошено только --deadline-window yesterday
- **THEN** она входит в результат; фильтр не означает фактическую дату закрытия

### Requirement: Server date filtering and API policy
Новые временные выборки SHALL передавать диапазоны в классический tasks.task.list REST1 через >=DEADLINE и <DEADLINE. --group-id SHALL отображаться в GROUP_ID этого маршрута. REST3 deadline filter и скрытый fallback MUST NOT использоваться. strict-rest3 SHALL отклонять маршрут до запросов с policy-denied/exit4.

#### Scenario: Explicit classic route
- **WHEN** task:list --group-id 42 --deadline-window today выполняется при допускающей REST1 политике
- **THEN** API получает GROUP_ID=42 и обе границы DEADLINE; metadata показывает REST1 и фактически рассчитанный интервал

#### Scenario: Strict REST3 policy
- **WHEN** новый временной фильтр задан при strict-rest3
- **THEN** CLI возвращает policy-denied/exit4 до сетевых запросов; локальная фильтрация не подменяет серверную

### Requirement: Filter combinations and invalid input
Временные критерии SHALL комбинироваться через AND с проектом, ролью, названием и допустимым статусом. Неизвестные/повторные windows, неверный timezone, timezone без временного режима и смешение новых временных флагов с --due-before либо --where/--where-file SHALL приводить к usage-error/exit2 до запросов.

#### Scenario: Search by title and deadline
- **WHEN** task:find --title "отчёт" --group-id 42 --deadline-window this-month --overdue выполняется
- **THEN** совпадают все критерии; title остаётся буквальной Unicode подстрокой, формат результата — список id/title

#### Scenario: Explicit invalid combination
- **WHEN** --deadline-window today задан вместе с --due-before, advanced --where, неизвестным timezone либо повторным window
- **THEN** CLI возвращает usage-error/exit2 без API и называет неверный аргумент

### Requirement: Public skill translation
Skill SHALL переводить user stories в canonical public CLI arguments без самостоятельного расчёта границ или прямых API/SDK/MCP вызовов. «Должны были закрыть» SHALL означать deadline-window вместе с overdue; фактическая дата закрытия SHALL отличаться и оставаться вне этого интерфейса.

#### Scenario: Natural language mappings
- **WHEN** пользователь просит дедлайн завтра, просроченные задачи либо задачи, которые должны были закрыть позавчера
- **THEN** skill выбирает соответственно --deadline-window tomorrow, --overdue либо --overdue --deadline-window day-before-yesterday, добавляя известные проект/timezone

#### Scenario: Capability missing in installed CLI
- **WHEN** установленный CLI ещё не предоставляет новые аргументы
- **THEN** skill сообщает отсутствующий public CLI contract без обхода через другой интерфейс

### Requirement: Temporal route field validation
Временной маршрут SHALL принимать общие документированные поля REST1 для --select/--order и нормализовать selected values в public CLI contract. Недоступные для маршрута поля SHALL приводить к usage-error/exit2 до API с перечнем допустимых. Non-temporal REST3 allowlists SHALL сохраняться.

#### Scenario: Unsupported temporal projection
- **WHEN** task:list --overdue получает --select chat или обе команды получают --order responsibleId:asc
- **THEN** CLI возвращает usage-error/exit2 до API с допустимыми полями REST1; не отправляет придуманные поля и не выполняет скрытую REST3 hydration
