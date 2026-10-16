# Design

## Context
См. proposal.md. Неизменённая dev 0caf9db проверена на bitrix24-php-sdk-playground.bitrix24.ru. Оба webhook (1,16) возвращают sonet_group, без sonet. Прямой sonet_group.user.get успешно прочитал участников собственного проекта 236, а нормальный CLI с writable config-dir дал scope-missing sonet для всех пяти целей. Проект удалён, задач создано 0. Исходные обезличенные evidence сохранены отдельно.

## Goals / Non-Goals
Исправить ложный отказ. Не расширять webhook permissions, не обходить guard и не подменять бизнес-команду SDK-назначениями. Live-приёмка после scope-fix выявила также серверный сброс deferred/in_progress в pending при смене ответственного. Его обработка включена в исправление.

## Decisions
- ScopeManifest использует фактический `sonet_group`; hash revision уже вычисляется из mapping и сам инвалидирует старый manifest.
- Проверки: реальные grants принимаются; отсутствие sonet_group сохраняет отказ; прежний выдуманный sonet не заменяет реальный grant. Все прочие scope gates сохраняются.
- Live runner создаёт только собственный проект/задачи, вызывает публичный CLI с writable config-dir и проверяет readback. Особо проверяются completed/supposedly_completed и native result. Все fixtures удаляются, доказательства обезличиваются.
- В существующем selective integration case fixture переводится в deferred SDK-хелпером. Общий invoke() тестового класса разрешает только task:handover; вызов им task:defer ошибочно падал до API. Это исправление подготовки fixtures, а не изменение runtime lifecycle.

## API evidence
16.10.2026 effective config bitrix24_dev проверен, MCP отвечал на поиск и детали. [sonet_group.user.get](https://apidocs.bitrix24.ru/api-reference/sonet-group/members/sonet-group-user-get.html) документирует ID integer и ROLE A/E/K; MCP называет scope sonet. Это расходится с live scope и каталогом scopes установленного b24phpsdk, где присутствует sonet_group. Исправление опирается на фактический разрешённый вызов и каталог SDK; документационное расхождение сохраняется явно.

## Risks / Trade-offs
Успешный scope gate не гарантирует права записи; membership, action permissions, required result и итоговое чтение остаются обязательными. При невозможности конкретного перехода фиксируется live failure, а не успешная приёмка.

## Assignment status reset
Live project 238, owned task 6390: после update только responsibleId deferred сменился на pending; задача 6400 показала in_progress → pending. После подтверждения нового ответственного и проекта CLI допускает исходный статус либо pending. Иное изменение остаётся конфликтом. Из фактического pending выполняется ограниченный штатный план к item.targetStatus — явной цели либо исходному статусу. Проверка перед назначением остаётся точной. Preview и routes включают условное восстановление даже без target-status и при равенстве source/target. Контроль/result/права проверяются тем же lifecycle service, без повторов и скрытого отката.
