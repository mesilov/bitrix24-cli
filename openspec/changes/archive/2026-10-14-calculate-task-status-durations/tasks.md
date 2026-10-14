# Tasks

## 1. Status evidence and completeness contract

- [x] 1.1 Подтвердить через официальный MCP и disposable SDK fixture фактические REAL_STATUS/STATUS значения, повторное открытие, NEW/начальный статус, равные timestamps и navigation; сохранить обезличенный receipt с API version/ролью/ограничениями. Проверка: receipt различает status events, mapping и подтверждённую/неподтверждённую полноту; неизвестное не объявлено доказанным.
- [x] 1.2 Дополнить внутренний history collection result evidence для conflict IDs/raw fields/ordering, сохранив gateway/scanner budget и публичный history:list. Проверка: unit fixtures conflict/duplicate/page-error/navigation/budget и regression ListTaskHistoryCommand contracts; описание ограничений в development docs обновлено вместе с изменением.

## 2. Pure calendar duration calculation

- [x] 2.1 Добавить чистый калькулятор входов/выходов, no-op и повторных открытий, timezone-aware instants и asOf. Проверка: фиксированный пример design даёт pending=30/in_progress=50/completed=40/total=120; два reopen цикла, равные даты/offset/DST и historical cutoff покрыты unit cases; описать семантику интервалов рядом с калькулятором.
- [x] 2.2 Добавить unknown boundary/partial rules, null exact totals и отдельно elapsedBetweenEventsSeconds. Проверка: missing NEW/initial/tail, from/to gap, invalid dates/status, conflicting IDs и partial source с согласованными соседями не дают точных сумм или ложного lower bound; соответствующие unit cases и пояснение JSON semantics присутствуют.

## 3. CLI, snapshots and traceability

- [x] 3.1 Добавить read-only task:status:durations request/handler/command, единый asOf до API, REST1 history и REST3 контрольные get(status/statusChanged/changed), без вложенного CLI и мутаций. Проверка: deferred input validation, profile/API-policy/strict-rest3/cancel/error contract unit cases и race/mismatch fixtures; русская командная справка описывает read routes и границы.
- [x] 3.2 Зарегистрировать команду и result presenter/human/plain вывод, согласовать delta inventory с актуальной dev, сохранив lifecycle и task:my без удаления команд. Проверка: offline help/list/catalog/JSON/TSV tests; README, docs/cli-command-map.md, docs/task-jtbd.md PM-02/AM-03 со статусом/filename теста/датой/source SHA и CHANGELOG #121 обновлены в том же MR, ссылки проверены.
- [x] 3.3 Добавить StatusDurationsTaskCommandTest.php с собственной Group command:task:status:durations/happy-path, SDK fixture/readback/cleanup и двумя повторными открытиями. Проверка: live receipt совпадает с доказанными timestamps/суммами; отдельный navigation partial case требует null totals/точную reason/exit3. Недоступность доказательства exact totals остаётся явно непроверенной, а skip/partial не заменяют exact happy-path.

- [x] 3.4 По запросу пользователя добавить и выполнить live-сценарий pending → in_progress → supposedly_completed → pending (disapprove) → in_progress → supposedly_completed → completed (approve). Проверка: после каждого SDK-действия REST3 readback и сырая история с выделением новых ID; отдельный JSON receipt, новые/старые события не смешаны, production команда только читает, cleanup успешен. Подтверждённый код 4 сопоставлен с supposedly_completed; unit/live checks отличают известный mapping от неподтверждённой полноты. Это исследование формы истории, не замена exact acceptance 3.3.

- [x] 3.5 Исследовать старые NEW/DELETE и полноту на уровне HTTP → SDK → adapter: изоляция двух собственных задач, before-create/after-delete наблюдения, start/filter/order, >50 событий и контроль полноты fixture. Проверка: history-investigation-check.md/fixture.json подтверждают server-origin и legacy TASK_ID, 60/60 изменений fixture; live 1 test/22 assertions, owned cleanup успешен. Полнота произвольного источника и причина серверного связывания старых строк не доказаны; подготовлен bitrix24-history-support-reproducer.md. Exact gates не ослаблены.

- [x] 3.6 Добавить отдельный live review-cycle test с паузой 5 секунд перед каждым переходом и перед asOf в completed. Проверка: monotonic delay не меньше 5 секунд; REST3 readback/новые STATUS события после каждого действия; сохранён фактический JSON task:status:durations, наблюдаемые промежутки не объявлены exact totals; fixture удалена.

- [x] 3.7 Читать REST3 list(id filter, id/created) до/после истории, проверить совпадающий ID/неизменный created и отделять строго более ранние события в excludedHistoryEvidence. Начальная граница — created, а не поздний NEW. Проверка: старые NEW/DELETE/STATUS исключены только при доказанной границе, ошибки/изменения created/равенство/плохие даты остаются partial, новые read routes объявлены, history:list не изменён.
- [x] 3.8 Допустить только подтверждённый положительный STATUS subset 2/3/4/5 как lifecycle transitions; REAL_STATUS сохраняет приоритет и дедупликацию. Проверка: мета/неизвестные значения и неподтверждённые aliases остаются partial; официальный numeric subset + live readbacks трассированы.
- [x] 3.9 В controlled five-second fixture сохранить created и проверить полный известный набор event IDs, initial/readbacks/cleanup; получить точные суммы чистым production calculator с явно bounded controlled-actions coverage. Проверка: суммы совпадают с независимым расчётом и created→asOf, обычный CLI без source completeness остаётся partial. Это не замена exact CLI acceptance 3.3.

- [x] 3.10 По запросу пользователя выполнить долгий review/reopen сценарий: восемь циклов, 55 статусных переходов, пауза 5 секунд; disapprove и renew возвращают на предыдущие этапы. Проверка: incremental history/readbacks/known IDs, >50 STATUS rows, сверка ordinary CLI/controlled sums, cleanup и отдельный receipt; новые гарантии полноты не выводятся только из длины теста.

- [x] 3.11 Исправить documented next exhaustion / optional total, сохранив counter contradiction/page-error/no-progress/budget и current lifetime gates. Проверка: официальный MCP article receipt, paginated/terminal unit fixtures, ordinary CLI exact two reopenings и long >50 status history, live race partial и новая pending task; history:list shape сохранён, исправленная completeness описана.

## 4. Cross-contract verification

- [x] 4.1 Выполнить целевые unit/integration и обязательные repository checks, openspec strict validate и openspec-verify-change. Проверка: итоговый receipt разделяет offline arithmetic, CLI contract, portal completeness и CI; каждое невыполненное acceptance условие оставлено открытым.

## Workflow follow-up

- После реализации отправить MR в dev со связанным #121, свежей интеграцией origin/dev и содержательной CHANGELOG правкой.
- После подтверждённого merge/закрытия issue архивировать change, доставить архив отдельным MR, обновить issue path и удалить только чистый связанный worktree согласно maintainer skill.
