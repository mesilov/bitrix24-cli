## Проблема и JTBD

Нужен один проверяемый недельный отчёт по проекту. AM-03 описывает общую сводку, но не определяет четыре показателя и период; task:report пока отсутствует в runtime.

**PM-09 — Недельный отчёт**
- Когда: подвожу итоги недели выбранного проекта.
- Хочу: получить список закрытых задач, список задач на контроле, количество новых задач и количество задач в работе.
- Чтобы: оценить выполненную работу, очередь приёмки и текущую нагрузку.
- Признак успеха: четыре показателя проверяемы; видны период, timezone, ограничения доступа и полнота.
- По умолчанию отчёт охватывает **весь выбранный проект**. Необязательный фильтр исполнителя сужает все четыре показателя.

## Планируемый интерфейс

```sh
b24cli task:report --group-id 234 --timezone Asia/Bishkek
b24cli task:report --group-id 234 --responsible 14 --week 2026-10-12 --timezone Asia/Bishkek --json
```

- --group-id ID обязателен. Без --responsible нет скрытого фильтра текущего пользователя/default.task; с --responsible проект и текущий ответственный объединяются через AND.
- --week this-week|last-week|YYYY-MM-DD, по умолчанию last-week. Дата выбирает содержащую её календарную неделю.
- Неделя: [понедельник 00:00, следующий понедельник 00:00) с учётом DST. --timezone IANA, default TZ/PHP с явным выводом фактического timezone.
- Один reportAt на команду; текущая неделя ограничивается reportAt; будущая неделя — usage-error.
- Общий конечный --max-scan N (default 10000), human/plain/JSON.

## Четыре показателя

| Показатель | Правило |
| --- | --- |
| Список закрытых | Текущий completed и фактическая closedDate в выбранной неделе. Повторно открытые не включаются; считается задача, а не каждое событие закрытия. |
| Список ожидающих контроля | Текущий supposedly_completed независимо от недели создания/передачи на контроль. |
| Количество новых | Уникальные доступные задачи с createdDate в периоде, независимо от текущего статуса. Новые означают созданные, а не поставленные конкретным автором. |
| Количество в работе | Уникальные текущие in_progress; pending/deferred/контроль не включаются. |

Два текущих показателя отражают чтение при формировании отчёта, включая отчёт за прошлую неделю. Восстановление ответственного/проекта/статусов на конец недели и атомарный snapshot не входят. Видимые задачи не объявляются всеми задачами портала.

## Контракт и ограничения

- Read-only tasks.task.list REST1, заранее объявленный route и scope task. Native filters GROUP_ID, optional RESPONSIBLE_ID, REAL_STATUS=3/4/5, CREATED_DATE/CLOSED_DATE.
- Не подменять REAL_STATUS полем STATUS, а фактическое закрытие дедлайном. Не отправлять REST3 имена в REST1.
- Strict-rest3 отклоняет план до credentials/API (policy-denied/exit 4); scope validation сохраняется; записей и fallback нет.
- Четыре bounded потока с server filters; единый бюджет, per-section completeness, deduplication и проверка конфликтующих наблюдений.
- При partial — exit 3, неизвестный точный count=null, подтверждённые items/observedCount и причины сохраняются. Отказ доступа не превращается в нулевой успешный отчёт.
- Организационные отделы, история всех закрытий, рассылки, расписание, XLSX/PDF и трудозатраты не входят.

## Приёмка

- [ ] Проверены calendar week/timezone/DST/границы года, валидность дат и один reportAt.
- [ ] Все четыре показателя проверены для всего проекта и фильтра конкретного ответственного.
- [ ] Покрыты old awaiting-control, created+closed в одной неделе, reopened и status/date boundary cases.
- [ ] Проверены >50 строк, общий scan budget, его точная граница, дубликаты/конфликты, page-error, missing fields, access errors и partial/exit 3.
- [ ] Native REST1 payload, REAL_STATUS, read policy, scope validation, strict-rest3 и cancellation проверены.
- [ ] Human/plain/JSON, помощь, PM-09/AM-03, CLI map, каталог и integration coverage согласованы.
- [ ] Verify/offline CI/live evidence разделены; завершение включает архивирование и обновление трассировки.

## OpenSpec

- Change: `add-weekly-task-report`
- Путь: `openspec/changes/add-weekly-task-report/`
- Артефакты: proposal.md, design.md, tasks.md, delta specs task-weekly-report, task-workflows (PM-09), task-mvp-scope.
- Подготовка JTBD/плана не является реализацией команды; runtime-каталог пока не меняется.
- При архивировании обновить этот путь на фактический архив и проверить обратную ссылку из proposal.

## Связи и источники

[#141](https://github.com/mesilov/bitrix24-cli/issues/141) расширяет общие относительные даты/фильтры создания и завершения; недельный отчёт выделен отдельно и не требует расширять scope #141.

Официальный MCP bitrix24_dev проверен 16.10.2026 (search → method details); native REST1 имена и REAL_STATUS сверены по [классическому list](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list.html) и [полям задачи](https://apidocs.bitrix24.ru/api-reference/tasks/fields.html). Это документационная проверка, не live-приёмка.
