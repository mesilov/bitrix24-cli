# RC 0.1.0: область приёмки и ограничения

Решение пользователя от **12.10.2026**: внешний fork-тест #4 и непроверенные части portal acceptance #10 перенесены в milestone **0.2.0**. Они исключены из обязательной приёмки RC 0.1.0 и остаются открытыми проверками; перенос не является новым доказательством работы CLI.

Этот документ определяет границы первой сборки 0.1.0 из `dev`.
[Возможности](../CHANGELOG.md#010--2026-10-12),
[аудит issues/OpenSpec](../openspec/changes/archive/2026-10-12-prepare-rc-0-1-0/audit.md).
Профили подключения #19 и исправление первого запуска macOS #23 поставлены в `dev`;
исходные acceptance records и новая native CI matrix учитываются отдельно.

## Что подтверждено исходным evidence

- Именованные профили/credentials/default.task #19 проверены на исходных native Linux/macOS/Windows сборках; 20/20 tasks, актуальная connection-configuration и архив присутствуют в `dev`. [Отчёт #19](../openspec/changes/archive/2026-10-12-add-cross-platform-connection-profiles/verification.md).
- Исправление macOS #23: исходный downloaded artifact `f627f34` прошёл browser/quarantine/штатное разрешение конкретного файла и version/help/list на macOS 15.5 (24F74); strict codesign и checksum сохранились. Это исходное доказательство #23, а не повтор browser acceptance новой сжатой сборки. [Отчёт #23](../openspec/changes/archive/2026-10-12-fix-macos-first-launch/verification.md).
- Сжатие пакетов и native acceptance реализованы отдельно: [отчёт #37](../openspec/changes/archive/2026-10-12-compress-standalone-binaries/verification.md). CI на выбранном RC source SHA должен подтвердить их совместную работу с #19.

- Четыре линтера работают локально и в реальных push/PR внутри репозитория; внешнего fork-run пока нет. [Исходный отчёт #4](../openspec/changes/archive/2026-10-12-add-ci-linters/verification.md).
- Все 30 команд задач имеют отдельный integration class и собственный live happy-path. Последний исходный portal record от 11.10.2026: **42 tests / 395 assertions, 41 passed, 1 restricted-role skip**; собственные fixtures очищены. [Отчёт #10](../openspec/changes/archive/2026-10-12-design-task-console-architecture/implementation-verification.md), [sanitized evidence](../openspec/changes/archive/2026-10-12-design-task-console-architecture/portal-per-command-evidence-2026-10-11.json).
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

## Проверка сборки кандидата

1. Выбранный source SHA `dev`, зелёные применимые tests/schema/quality/worktree checks и успешная native packaging matrix Linux amd64/macOS arm64/Windows amd64 на этом SHA.
2. Native profiles/storage/ACL/offline/secret/package verification на выбранном source SHA поверх завершённого [#19](https://github.com/mesilov/bitrix24-cli/issues/19).
3. Strict codesign/version/help/list для конечного сжатого macOS executable. Исходная browser/quarantine приёмка [#23](https://github.com/mesilov/bitrix24-cli/issues/23) записана выше; CI smoke не заменяет фактическое скачивание через браузер. Developer ID/notarization пока не выполняются.
4. Архивы и SHA256SUMS одного CI run, зафиксированные source/run refs и фактические результаты приёмки. Текущая dev-сборка имеет версию `dev-<SHA>`; перенос этих проверок не создаёт version tag или GitHub Release.

## Трассировка переноса

- #4 остаётся в 0.1.0 с выполненной реализацией/внутренним CI; внешняя fork-часть — [#41](https://github.com/mesilov/bitrix24-cli/issues/41), [proposal](../openspec/changes/verify-external-fork-ci/proposal.md).
- #10 остаётся в 0.1.0 с выполненным research/runtime/happy-path scope; непроверенные части — [#42](https://github.com/mesilov/bitrix24-cli/issues/42), [proposal](../openspec/changes/complete-task-portal-acceptance/proposal.md).
- В новых changes все future tasks остаются unchecked. Завершённые add-ci-linters (10/10) и design-task-console-architecture (42/42) проверены и архивированы 12.10.2026; общий статус их архива не является доказательством перенесённых тестов.
