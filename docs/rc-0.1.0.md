# RC 0.1.0: область приёмки и ограничения

Решение пользователя от **12.10.2026**: внешний fork-тест #4 и непроверенные части portal acceptance #10 перенесены в milestone **0.2.0**. Они исключены из обязательной приёмки RC 0.1.0 и остаются открытыми проверками; перенос не является новым доказательством работы CLI.

Этот документ определяет границы кандидата из `dev`. Он не подтверждает завершение текущей CI-сборки, готовность Release или прохождение #19/#23.

## Что подтверждено исходным evidence

- Четыре линтера работают локально и в реальных push/PR внутри репозитория; внешнего fork-run пока нет. [Исходный отчёт #4](../openspec/changes/add-ci-linters/verification.md).
- Все 30 команд задач имеют отдельный integration class и собственный live happy-path. Последний исходный portal record от 11.10.2026: **42 tests / 395 assertions, 41 passed, 1 restricted-role skip**; собственные fixtures очищены. [Отчёт #10](../openspec/changes/design-task-console-architecture/implementation-verification.md), [sanitized evidence](../openspec/changes/design-task-console-architecture/portal-per-command-evidence-2026-10-11.json).
- Эти результаты относятся к проверенному порталу, роли и source refs из evidence; они не подтверждают все permission combinations и полноту всех данных.

## Ограничения RC

| Граница | Что известно | Проверка 0.2.0 |
| --- | --- | --- |
| Внешний fork PR | Совместимость подтверждена конфигурацией; реальный fork под другим владельцем не проверен. GitHub может требовать owner approval. | [#41](https://github.com/mesilov/bitrix24-cli/issues/41), `verify-external-fork-ci`, исходная fork-часть task 2.2. |
| Запрещённые роли | Полная denied-role матрица task/chat/time/checklist/participants не подтверждена; restricted-role case был skipped. | [#42](https://github.com/mesilov/bitrix24-cli/issues/42), `complete-task-portal-acceptance`, остатки 5.2–5.4 и 2.3. |
| История и время | Navigation/completeness history и elapsedTime доказаны частично; точные partial причины сохраняются. Успешное чтение не доказывает полную историю или общий итог времени. | #42, tasks 3.2 и 4.1. |
| Вложения | Прикрепление подтверждено ACK; независимый membership readback не подтверждён, REST3 get возвращал fileIds=null. | #42, task 3.3. |
| Передача задачи | Reassignment проверен только на текущего пользователя; передача второму пользователю не доказана. | #42, task 3.3. |
| Native REST3 auditors | Filled projection давала INTERNAL_SERVER_ERROR на проверенном портале. task:show использует заранее выбранные REST1 role IDs/user.get companion reads; task:list native limitation сохраняется. Композиция не является атомарным task/user snapshot. | #42, task 3.4. |

## Контракты, которые сохраняются

Перенос проверок не ослабляет permission/binding gates, strict-rest3, подтверждение удаления, partial/exit3, outcomeUnknown и cancellation/exit130. Нет error-triggered API fallback/retry. 29 post-MVP операций, включая исключённые lifecycle/result workflows, остаются за границами RC.

## Что остаётся обязательным для кандидата

1. Выбранный source SHA `dev`, зелёные применимые tests/schema/quality/worktree checks и успешная native packaging matrix Linux amd64/macOS arm64/Windows amd64 на этом SHA.
2. Native profiles/storage/ACL/offline/secret/package verification и завершение [#19](https://github.com/mesilov/bitrix24-cli/issues/19).
3. Проверка свежего macOS artifact после скачивания через браузер с quarantine, strict codesign и version/help/list, со штатным разрешением конкретного приложения при необходимости; завершение [#23](https://github.com/mesilov/bitrix24-cli/issues/23).
4. Архивы и SHA256SUMS одного CI run, зафиксированные source/run refs и фактические результаты приёмки. Текущая dev-сборка имеет версию `dev-<SHA>`; перенос этих проверок не создаёт version tag или GitHub Release.

## Трассировка переноса

- #4 остаётся в 0.1.0 с выполненной реализацией/внутренним CI; внешняя fork-часть — [#41](https://github.com/mesilov/bitrix24-cli/issues/41), [proposal](../openspec/changes/verify-external-fork-ci/proposal.md).
- #10 остаётся в 0.1.0 с выполненным research/runtime/happy-path scope; непроверенные части — [#42](https://github.com/mesilov/bitrix24-cli/issues/42), [proposal](../openspec/changes/complete-task-portal-acceptance/proposal.md).
- В новых changes все future tasks остаются unchecked. Архивирование старых changes — отдельное действие после проверки пересмотренной области; общий статус их архива не является доказательством перенесённых тестов.
