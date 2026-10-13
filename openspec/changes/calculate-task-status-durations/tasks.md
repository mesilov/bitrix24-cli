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
- [ ] 3.3 Добавить StatusDurationsTaskCommandTest.php с собственной Group command:task:status:durations/happy-path, SDK fixture/readback/cleanup и двумя повторными открытиями. Проверка: live receipt совпадает с доказанными timestamps/суммами; отдельный navigation partial case требует null totals/точную reason/exit3. Недоступность доказательства exact totals остаётся явно непроверенной, а skip/partial не заменяют exact happy-path.

- [x] 3.4 По запросу пользователя добавить и выполнить live-сценарий pending → in_progress → supposedly_completed → pending (disapprove) → in_progress → supposedly_completed → completed (approve). Проверка: после каждого SDK-действия REST3 readback и сырая история с выделением новых ID; отдельный JSON receipt, новые/старые события не смешаны, production команда только читает, cleanup успешен. Подтверждённый код 4 сопоставлен с supposedly_completed; unit/live checks отличают известный mapping от неподтверждённой полноты. Это исследование формы истории, не замена exact acceptance 3.3.

## 4. Cross-contract verification

- [x] 4.1 Выполнить целевые unit/integration и обязательные repository checks, openspec strict validate и openspec-verify-change. Проверка: итоговый receipt разделяет offline arithmetic, CLI contract, portal completeness и CI; каждое невыполненное acceptance условие оставлено открытым.

## Workflow follow-up

- После реализации отправить MR в dev со связанным #121, свежей интеграцией origin/dev и содержательной CHANGELOG правкой.
- После подтверждённого merge/закрытия issue архивировать change, доставить архив отдельным MR, обновить issue path и удалить только чистый связанный worktree согласно maintainer skill.
