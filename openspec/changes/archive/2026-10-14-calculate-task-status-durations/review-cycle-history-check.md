# Возврат с контроля и успешное завершение — 14.10.2026

Задача **6306** создана SDK для теста с `needsControl=true`, отдельными creator/responsible и настроенным действующим пользователем. После теста удалена; cleanup подтверждён без ошибок. Это не denied-role матрица.

`testRejectedReviewThenSuccessfulCompletionRetainsIncrementalHistory` в StatusDurationsTaskCommandTest.php прошёл: **1 тест, 38 assertions**. Сырые ответы без имён/портала/секретов: [review-cycle-history-fixture.json](review-cycle-history-fixture.json).

| Действие | createdDate (offset +03:00) | ID события | field | value.from → value.to | REST3 readback |
| --- | --- | --- | --- | --- | --- |
| start | 2026-10-13T23:43:56+03:00 | 23576 | STATUS | 2 → 3 | in_progress |
| complete | 2026-10-13T23:43:58+03:00 | 23578 | STATUS | 3 → 4 | supposedly_completed |
| disapprove | 2026-10-13T23:43:59+03:00 | 23580 | STATUS | 4 → 2 | pending |
| start | 2026-10-13T23:44:01+03:00 | 23582 | STATUS | 2 → 3 | in_progress |
| complete | 2026-10-13T23:44:03+03:00 | 23584 | STATUS | 3 → 4 | supposedly_completed |
| approve | 2026-10-13T23:44:05+03:00 | 23586 | STATUS | 4 → 5 | completed |

## Что вернул портал

- Все шесть действий дали новые STATUS-события; код 4 дважды независимо совпал с supposedly_completed, возврат disapprove — 4→2, принятие approve — 4→5.
- REAL_STATUS не появился. next и total отсутствуют на baseline и после всех действий. Нет доказательства полноты общей истории.
- Baseline включал два старых события NEW/DELETE от 23.02.2026 и текущий NEW. Они сохранены отдельно; новый цикл выделялся сравнением ID с предыдущим чтением, без предположения о хронологии по величине ID.
- Snapshot после каждого действия подтверждает состояние и statusChanged; sequence order подтверждён контролируемыми действиями/инкрементальными чтениями именно этого теста, а не общей гарантией API.
- Калькулятор теперь распознаёт наблюдённый код 4. После правки false unknown-history-status/status-chain-gap/tail-state-unverified исчезли; remaining reasons: history-navigation-unverified, deleted-task-history, status-equivalence-unverified, ambiguous-creation-boundary.
- CLI вызвал только get/history/get (read); complete=false, точные totals=null, exit 3. Наличие всех контролируемых переходов в этом capture не заменяет lifetime exact acceptance 3.3.

## Повторный запуск

```sh
make integration TEST_ARGS="--filter testRejectedReviewThenSuccessfulCompletionRetainsIncrementalHistory"
```

Используется обычное подключение интеграционных тестов и B24CLI_TEST_OTHER_USER_ID либо B24CLI_TEST_EXECUTOR_WEBHOOK для разных ролей. Результат каждого запуска — var/cache/review-cycle-history.json; cleanup — var/cache/portal-evidence.jsonl.

Официальный MCP search/details: [start](https://apidocs.bitrix24.ru/api-reference/tasks/status/tasks-task-start.html), [complete](https://apidocs.bitrix24.ru/api-reference/tasks/status/tasks-task-complete.html), [disapprove](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-disapprove.html), [approve](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-approve.html). REST1 payload использует taskId; контрольные карточки REST3 get — id/select.
