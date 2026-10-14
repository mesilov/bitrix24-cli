# Design

## Context

Мотивация — в proposal.md. Исходное evidence `portal-per-command-evidence-2026-10-11.json` подтверждает 30 собственных happy-path, 41 passed + 1 restricted-role skip, 395 assertions. Непроверенные части прежних 5.2–5.4 и 2.3 отделяются от уже выполненных случаев; исходные JSON records не переписываются.

## Goals / Non-Goals

**Goals:** получить дополнительную role/completeness матрицу, независимый file readback и передачу задачи второму пользователю для 0.2.0.

**Non-Goals:** расширять 30-command MVP, возвращать 29 post-MVP операций, вводить новые REST routes или fallback/retry, обещать полноту там, где API не позволяет её доказать.

## Decisions

1. Использовать существующие per-command integration classes и SDK fixtures/readback/cleanup. Дополнять их restricted-role cases; подготовка данных не запускает чужую CLI-команду.
2. Роли и scopes подтвердить на согласованном втором webhook. Для каждого case записать API version, ресурс/владельца, expected/observed outcome и количество calls; credentials и персональные данные не публиковать.
3. Полноту history/time проверять на известном наборе собственных записей и документированной navigation. Не выводить complete=true только из наличия данных; сохранённое ограничение API может быть итогом проверки с явным partial/exit3.
4. Attachment ACK сверять с независимым membership readback, reassignment — со вторым пользователем. При недоступном readback зафиксировать недоказанную границу вместо положительного результата.
5. Native REST3 auditors и выбранную композицию task:show проверять раздельно. strict-rest3 продолжает отказывать non-v3 планам до credentials/API; native task:list limitation сохраняется до отдельного доказательства/исправления.
6. Все тестовые mutations ограничить собственными согласованными fixtures и проверить cleanup. Найденные дефекты, требующие изменения поведения, оформить отдельным issue/change.

## Risks / Trade-offs

- [Нет restricted-role webhook или второго пользователя] → считать связанные cases not verified и оставить tasks открытыми; не заменять их обычным happy-path.
- [Portal schema/permissions и navigation зависят от подключения] → фиксировать дату/роль/API/limits и не распространять вывод на все порталы.
- [Независимый membership readback недоступен] → сохранить ACK-only границу с точным основанием.

## Migration Plan

Новые live records добавляются отдельно от исходного evidence; после будущего apply обновляются verification, RC limits и обе стороны issue ↔ change. Исходные task IDs и статусы сохраняются в таблице переноса. Архивировать только после фактической проверки и фиксации оставшихся платформенных/API границ.

## Execution and delivery decision 2026-10-14

The user supplied BITRIX24_SECOND_USER_WEBHOOK; profile returns user 16, distinct from owner 1. The access preflight returns admin=true on the owner fixture, so the connection is suitable for reassignment but not denied-role proof. Optional B24CLI_TEST_RESTRICTED_WEBHOOK must identify a non-admin actor; tests skip with the actual preflight record rather than treating an administrator as restricted. The user then stopped further checks and authorized dev merge of the partial results. No additional live run is part of this delivery. Unexecuted cases and the time-navigation defect remain open.

## Final user decision 2026-10-14

User stopped further checks, authorized merge into dev, and explicitly requested closing #42. Deliver and archive the partial tests/evidence with the ten original compound tasks cancelled and unchecked. This closure does not assert complete role/completeness acceptance, file membership, or a fix for time navigation. Runtime behavior and API route scope remain unchanged.
