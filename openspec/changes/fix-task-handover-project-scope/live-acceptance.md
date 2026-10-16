# Live acceptance EMP-09 — 16.10.2026

Исправление #170 проверено на тестовом портале `bitrix24-php-sdk-playground.bitrix24.ru`. **18/18 live-проверок и 4/4 repository integration tests прошли без skips.** В число проверок входят ожидаемые отказы/partial, это не 18 успешных передач.

База: `0caf9db62fce3f91852aafc77fd9b25cb3f9bde2`; точные SHA256 трёх исполняемых файлов сохранены в [live-receipt.json](live-receipt.json). Запуск — исходники PHP 8.4.26 в Docker, обычный ApplicationFactory/public CLI, writable config-dir, scope validation `fresh`; standalone/release-бинарник не проверялся. Токены не включены в отчёт.

## Первоначальные дефекты

1. На неизменённой dev все пять целей блокировались ложным требованием `sonet`; фактический grant `sonet_group` разрешал чтение состава проекта. Первый проект 236 удалён, задач в нём не создавали.
2. После исправления scope первая матрица дала 9 PASS / 6 FAIL: сервер при назначении сбрасывает deferred/in_progress/supposedly_completed в pending. Все fixtures первой попытки удалены. Код исправлен и **вся** матрица выполнена заново; неудачные попытки не засчитаны как финальная приёмка.

## Финальная матрица

| Сценарий | Проверка | Exit CLI | Native actions | Actual status |
|---|---|---:|---|---|
| deferred-filter-and-ID-exclusions | PASS | 0 | defer | deferred |
| source-filter-pending | PASS | 0 | — | pending |
| source-filter-in_progress | PASS | 0 | start | in_progress |
| source-filter-deferred | PASS | 0 | defer | deferred |
| source-filter-supposedly_completed | PASS | 0 | complete | supposedly_completed |
| target-pending | PASS | 0 | — | pending |
| target-in_progress | PASS | 0 | start | in_progress |
| target-deferred | PASS | 0 | defer | deferred |
| target-completed | PASS | 0 | complete | completed |
| target-supposedly_completed | PASS | 0 | complete | supposedly_completed |
| controlled-review-to-completed | PASS | 0 | complete | completed |
| controlled-completion-and-approval | PASS | 0 | complete, approve | completed |
| required-result-missing-partial | PASS | 3 | complete | pending |
| required-result-present-completed | PASS | 0 | complete | completed |
| review-without-control-rejected-before-assignment | PASS | 3 | — | None |
| creator-cannot-force-review-through-native-complete | PASS | 3 | complete | completed |
| employee-self-mode | PASS | 0 | defer | deferred |
| manager-third-party-transfer | PASS | 0 | defer | deferred |

Для каждой команды сначала выполнялся dry-run: точный набор ID назначений сверялся с собственными fixtures, запись отсутствовала, карточки совпадали с исходными. Положительные результаты сверялись отдельным SDK-чтением под пользователями 1 и 16. Исключённая и не соответствующая фильтру задачи проверялись неизменными. `--exclude-id 92888` проверялся вместе с реальным собственным исключением и повторным ID; задачу 92888 не переназначали.

В manager mode подключение 1 назначало задачи 16 → 1 и отдельно 16 → 14: руководитель отличается от обоих сотрудников. Self mode: подключение 16, без --from, 16 → 1. Для source=review использован отдельный автор 14, чтобы complete от нового исполнителя мог штатно вернуть review. Роль третьего автора — только fixture; передача по-прежнему между двумя участниками.

`controlled-completion-and-approval` подтвердил именно complete → approve. Отсутствующий обязательный native result даёт exit 3 с assignmentStatus=confirmed и transitionStatus=failed; существующий result позволяет завершить задачу. Отключённый контроль отклоняет review до назначения. При complete самим автором сервер сразу возвращает completed; CLI сохраняет частичный результат и не выдаёт его за review.

## Repository integration

Все четыре `HandoverTasksCommandTest` выполнены с main identity 16 и second identity 1 в том же изолированном проекте: self transfer, manager transfer, deferred selection/exclusion, explicit deferred target. Итоги, ID fixtures и cleanup записаны в live-receipt.json. Состав и права обычных проектов не менялись.

## Очистка и границы

Собственный проект 238 удалён; повторный sonet_group.get по ID вернул пустой список. Все 45 задач обеих custom-попыток удалены с ACK. Все integration fixtures удалены, cleanup failures отсутствуют. Вебхуки, private env/config и полный журнал хранятся только во временном локальном evidence bundle.

Эта приёмка подтверждает поведение для фактических прав этих пользователей на этом портале. Отдельный live отказ ограниченному non-admin пользователю, сетевые потери ответа, конкурентные изменения и побочные эффекты на подзадачи не моделировались; для них сохранены unit-контракты и границы руководства. Наличие scope не заменяет action-specific права.

## Проверки кода

- Unit: 893 tests, 16881 assertions.
- make check, четыре проверки make lint-all, cli-reference-check: PASS, 63 команды.
- OpenSpec --all --strict: 27 passed / 0 failed до архивирования.
- OpenSpec verify: см. verification.md.
