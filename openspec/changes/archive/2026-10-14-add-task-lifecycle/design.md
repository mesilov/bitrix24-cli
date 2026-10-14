# Design

## Context

Мотивация и scope — в [proposal.md](proposal.md). База planning: `origin/dev` / `4589588` на 13.10.2026. Сейчас `CommandCatalog` содержит 30 task-команд и 5 локальных connection-команд; lifecycle отсутствует. `FieldSchema::writable` запрещает raw status write. `PreparedOperation` отделяет preflight/plan от execute; `ApiPolicyGuard` сверяет весь список маршрутов до provider. `Rest3TaskAdapter` уже читает карточку и права, `SdkApiTransport` учитывает effect и неопределённость отправленной записи. Обычный mutation presenter не выводит status, поэтому для lifecycle нужен отдельный профиль результата.

### Синхронизация с dev — 14.10.2026

После обновления dev каталог включает 42 task-команды (30 исходных, четыре result, семь lifecycle и отдельно доставленную task:my по #118), четыре users-команды и шесть локальных команд. Сохраняются русская справка, явные --role фильтры своей роли, календарные дедлайны, теги, загрузки и id DESC. Проверка неизвестной группы через REST3 карточку применяется к REST3 scan, после остальных predicates; REST1 deadline scan сохраняет свой серверный GROUP_ID и полный route plan. Собственные задачи также можно явно выбирать через --role responsible, но task:my этот change не добавляет.

### Проверенные источники API

Официальная документация прочитана 13.10.2026. Это документированный контракт, а не доказательство работы методов на конкретном портале.

| Команда | Метод | Версия / payload | Назначение |
| --- | --- | --- | --- |
| `task:start` | [tasks.task.start](https://apidocs.bitrix24.com/api-reference/tasks/status/tasks-task-start.html) | REST1, `{taskId: integer}` | Начать работу |
| `task:pause` | [tasks.task.pause](https://apidocs.bitrix24.com/api-reference/tasks/status/tasks-task-pause.html) | REST1, `{taskId: integer}` | Приостановить выполнение |
| `task:defer` | [tasks.task.defer](https://apidocs.bitrix24.com/api-reference/tasks/status/tasks-task-defer.html) | REST1, `{taskId: integer}` | Отложить |
| `task:renew` | [tasks.task.renew](https://apidocs.bitrix24.com/api-reference/tasks/status/tasks-task-renew.html) | REST1, `{taskId: integer}` | Возобновить после завершения |
| `task:complete` | [tasks.task.complete](https://apidocs.bitrix24.com/api-reference/tasks/status/tasks-task-complete.html) | REST1, `{taskId: integer}` | Завершить / передать на контроль |
| `task:approve` | [tasks.task.approve](https://apidocs.bitrix24.com/api-reference/tasks/tasks-task-approve.html) | REST1, `{taskId: integer}` | Принять работу при контроле |
| `task:disapprove` | [tasks.task.disapprove](https://apidocs.bitrix24.com/api-reference/tasks/tasks-task-disapprove.html) | REST1, `{taskId: integer}` | Вернуть на доработку при контроле |

У каждого mutation документирован `result.task` после операции. Все TASK_ID положительные integer; ID в legacy ответе может быть строкой. `approve` и `disapprove` документированы для постановщика/администратора. Общие описания других методов не заменяют проверку права конкретного действия.

- [REST3 tasks.task.get](https://apidocs.bitrix24.com/api-reference/tasks/tasks-task-get-rest-v3.html): `id` integer и `select`, результат `result.item`.
- [REST3 поля](https://apidocs.bitrix24.com/api-reference/tasks/fields-rest-v3.html): `status` string (`pending`, `in_progress`, `supposedly_completed`, `completed`, `deferred`, `declined`); `needsControl`, `requireResult`, `containsResults`, `autocompleteSubTasks` boolean.
- [REST3 tasks.task.access.get](https://apidocs.bitrix24.com/api-reference/tasks/tasks-task-access-get.html): `id` integer; отдельные boolean ключи `start`, `pause`, `defer`, `renew`, `complete`, `approve`, `disapprove`.
- [REST3 tasks.task.result.list](https://apidocs.bitrix24.com/api-reference/tasks/result/tasks-task-result-list-rest-v3.html): обязательный filter по `taskId`; `select`, `order`, `pagination`; результат `result.items`.
- [Native result object](https://apidocs.bitrix24.com/api-reference/tasks/result/tasks-task-result-add.html): `id`/`taskId` integer и `status` string `open`/`closed`. Статус результата отличается от статуса задачи; документация не определяет здесь универсальное правило «только open допускает complete».

## Goals / Non-Goals

**Goals:** одна mutation на выбранное действие, точный API/version/payload, проверенный preflight, независимое чтение статуса и явные границы подтверждения. Все семь команд следуют существующим правилам bootstrap, connection, JSON, dry-run и cancellation.

**Non-Goals:** локальная копия всей серверной state machine, автоматическое исправление прав/статуса, создание результатов или замечаний, таймеры, разрешение произвольного REST, запись raw status. Чтение native results — внутренний preflight complete, не новая пользовательская result-команда. Полная приёмка PM-05/EMP-05 не объявляется закрытой только lifecycle, так как предъявление результата и замечания имеют отдельный scope.

## Decisions

### 1. Семь явных команд и узкий gateway

Добавить семь Command-классов по существующему соглашению `AsCommand`, отдельные registration/DI entries и общий request с валидированным `TaskReference` и закрытым набором action. `TaskLifecycleService` владеет preflight/plan/result; узкий `TaskLifecycleGateway` с REST1 adapter выполняет один заранее выбранный метод. Общий gateway не принимает произвольное имя метода от CLI. `ApiTransport` и журнал API вызовов остаются едиными.

Альтернатива — `task:update --status` или generic `task:status:set`; отклонена, потому что теряет семантику действий и снимает действующий запрет status patch. Общий Command-класс с aliases осложняет отдельные command/integration контракты.

### 2. Полный route plan проверяется до подключения

Для каждого действия объявить REST3 `tasks.task.get` и `tasks.task.access.get`, соответствующий REST1 `tasks.task.<action>`; для complete также условный REST3 `tasks.task.result.list`. Mutation отмечается effect=write. Ни одно действие не использует `tasks.task.update` или deprecated `task.item.*`. В `task-v3` допуск точечный, в `strict-rest3` полный план даёт exit4 даже для dry-run и ещё до REST3 preflight. Help/list остаются offline.

Альтернатива — сначала REST3 запрос, а затем legacy при ошибке; отклонена, поскольку это скрытый fallback с неподтверждённой семантикой.

### 3. Preflight подтверждает задачу, право и обязательный результат

Через REST3 get выбрать `id`, `status`, `needsControl`, `requireResult`, `containsResults`, `autocompleteSubTasks`. Проверить ID, известный status и строгие boolean значения; missing не преобразовывать в false. Через access.get потребовать boolean true именно action. false даёт permission-denied/exit1, отсутствующее/невалидное право — lifecycle-unavailable/exit4. Повреждённая форма/чужой ID — api-error/exit1 до записи. `approve`/`disapprove` дополнительно требуют needsControl=true и supposedly_completed; остальные переходы не дублируют серверную матрицу source-state, их допустимость определяет право и сам метод.

Для complete при requireResult=true вызвать result.list с `filter=[["taskId",TASK_ID]]`, `select=["id","taskId","status"]`, `order={"id":"ASC"}`, `pagination={"offset":0,"limit":1}`. Это проверка существования, не полный список результатов. Нужна минимум одна валидная запись с совпадающим taskId и документированным status; чужие/повреждённые rows не игнорируются. Пустой/недоступный ответ даёт required-result-unverified/exit4 с безопасной причиной. `containsResults` отдельно не разрешает complete. Не вводить предположение open-only; окончательную допустимость результата и race после чтения проверяет сервер при mutation. Portal cases контроля используют разных постановщика и исполнителя: другой creatorId с текущим подключением либо отдельный webhook исполнителя. Portal cases проверяют result semantics, включая повторное завершение после renew. Если live evidence покажет дополнительное условие, до изменения runtime уточнить этот change по evidence, не снимать gate.

Альтернатива — проверка только requireResult/containsResults; отклонена, так как не подтверждает native result нужной задачи. Result CRUD и создание сообщения не требуются для read-only preflight.

### 4. ACK и фактический статус — разные подтверждения

REST1 adapter принимает только объект task с положительным id выбранной задачи; boolean/null, отсутствие объекта и чужой ID не являются ACK. Не применять общий boolean ACK validator. После подтверждения один REST3 get с `id,status,needsControl,requireResult` предоставляет фактическую карточку. Не переводить legacy numeric status локально и не синтезировать статус из action или preflight needsControl. Если в промежутке другой пользователь изменил статус, вернуть состояние из post-read.

Успех имеет `data.operation={taskId,action,confirmed:true}` и `data.task={id,status,needsControl,requireResult}`, `meta.complete=true`, `meta.outcomeUnknown=false`, существующие schemaVersion/apiCalls/apiVersions. Новый lifecycle profile отображает plain TSV в порядке `taskId,action,confirmed,status,needsControl,requireResult`, human — те же факты с понятным описанием ожидания контроля. JSON сохраняет REST3 имена и значения. `meta.complete` — полнота выполнения/чтения, она не означает task.status=completed.

ACK с последующим failed/malformed/incomplete read даёт OperationResult/exit3: подтверждение сохраняется, `data.task` содержит целевой id и неизвестные поля null (достоверные post-read поля сохраняются), complete=false, outcomeUnknown=false и причину. Preflight status не становится post-state. Потерянный/malformed ACK отправленной записи — Failure/exit1, outcomeUnknown=true, без fabricated confirmed=true. Cancellation — exit130 и известные выполненные шаги; дальнейших запросов нет.

Альтернатива — выводить только operation confirmed или legacy response; отклонена, поскольку пользователю нужен единый актуальный REST3 status и различие supposedly_completed/completed. Цена решения — один дополнительный read после mutation и возможный честный partial.

### 5. Preview и побочные эффекты

PreparedOperation выполняет safe preflight до execute. Dry-run показывает action, taskId, текущее состояние/условия и предполагаемый маршрут mutation/post-read; post-read в preview не вызывается. Показать настройку autocompleteSubTasks и возможный контроль, не обещая результат. Отдельного подтверждения обычной записи нет, --force не требуется и не снимает gates.

Побочные эффекты таймера/времени, контроля/result, подзадач остаются серверными. CLI не запускает дополнительные записи для их исправления. Portal acceptance сравнивает собственные fixtures до/после с настройками `allowsTimeTracking`, `requireResult`, `needsControl`, `autocompleteSubTasks`. Полнота side effects за пределами проверенных fixtures не обещается.

### 6. Выборка по логическому статусу для трёх JTBD

Проверка исходников тега `0.1.0` и dev 13.10.2026 подтверждает наличие `task:list --responsible ID --group-id ID --status STATUS`: mapper собирает equality predicates, scanner объединяет их через AND. Числовой ID собственного пользователя позволяет получить свои задачи; отдельного `--me`, current-user discovery или implicit self scope в CLI нет. `default.task.responsibleId` применяется при создании задачи, не служит идентификатором текущего пользователя и не применяется к списку. Новую команду task:my и автоматическое определение пользователя в этом change не добавлять.

Примеры действующего синтаксиса (42 — собственный ID, 99 — другой исполнитель, 7 — группа/проект):

```sh
b24cli task:list --responsible 42 --status in_progress --all --json
b24cli task:list --group-id 7 --status supposedly_completed --all --json
b24cli task:list --responsible 99 --status deferred --all --json
b24cli task:list --group-id 7 --responsible 99 --status pending --all --json
b24cli task:list --where '{"groupId":7,"responsibleId":99,"status":"completed"}' --all --json
```

Под логическим статусом понимается точное поле `status` REST3 с шестью документированными string значениями из таблицы полей выше. Оно отличается от result.status, стадии проекта и числового статуса REST1. `--status` выбирает одно равенство, не набор/OR; дополнительные responsible/group/status/deadline predicates объединяются через AND, advanced --where не смешивается с convenient flags. [REST3 tasks.task.list](https://apidocs.bitrix24.com/api-reference/tasks/tasks-task-list-rest-v3.html) документирует server filter по id; responsibleId/groupId/status проверяются локально по selected полям при ограниченном обходе видимых подключению задач. `--all` снимает только limit вывода, max-scan=10000 по умолчанию сохраняется; доступ к задачам другого пользователя зависит от подключения, полнота видимой выборки не равна полноте проекта.

Портальная проверка группы 234 выявила: list возвращает groupId=null даже с group.id в select, тогда как get(group.id) возвращает настоящий ID. Для list/find с фильтром groupId заранее объявить REST3 get companion. Проверять карточку только для кандидатов, прошедших остальные predicates, если scalar groupId отсутствует/null; максимум один lookup на просмотренную задачу, не более max-scan. Корректный group object подтверждает ID, явный group=null — отсутствие группы. Недоступное/неполное чтение даёт group-membership-unverified/partial, а не empty complete success. Это фиксированная композиция REST3 чтений без error-triggered fallback, повторов и смены версии. Она увеличивает число запросов на порталах без groupId в списке; meta.apiCalls показывает каждый запрос.

Найденный validation gap: mapper сейчас пропускает произвольный --status и string/integer --where.status. Например, числовой код или опечатка сравнивается с REST3 строкой и может вернуть empty complete=true. В реализации change добавить единый набор REST3 task status значений для preflight/post-read и query validation. --status/--where.status допускают только эти строки; unknown/empty/numeric/array дают usage-error/exit2 до credentials/API, без aliases или перевода legacy кодов. Дополнительный REST-запрос ради проверки перечисления не нужен.

Новые сценарии дополняют существующие EMP-01, PM-02 и AM-02, а не увеличивают число JTBD за счёт тех же целей. Acceptance проверяет AND combinations, visibility, partial/limit и смену membership: complete с контролем попадает в supposedly_completed, approve — в completed, disapprove/renew/start/pause/defer проверяются по фактически прочитанному status. Не жёстко вычислять status из action. В ListTaskCommandTest переходы fixtures выполняются через SDK, чтобы каждый test case вызывал только свою CLI-команду.

## Risks / Trade-offs

- [Различия порталов/ролей] → live cases каждой команды через production provider и отдельная permission matrix; skip явно остаётся непроверенным случаем.
- [Изменение условий после preflight] → серверный отказ сохраняется, никаких повторных переходов; клиентские чтения не считаются блокировкой задачи.
- [Запись подтверждена, readback недоступен] → partial/exit3 с confirmed=true и неизвестным status, без обещания отката.
- [REST3 metadata/result support недоступны] → fail closed с причиной, без legacy read fallback для проверки результата.
- [autocompleteSubTasks и учёт времени] → preview показывает настройку, acceptance отдельно фиксирует реальные изменения подзадач/времени.
- [Соседние changes #12/#42] → не закрывать чужие задачи или полную portal/JTBD приёмку; rebase/merge dev и согласовать новые counts/исключения перед PR реализации.
- [Пустой список из-за неверного status либо недостаточных прав] → local enum validation; в output сохранять visible-task scope, complete/scanned/matched/returned/limitApplied. Не обещать полный список другого исполнителя при ограниченном доступе.

## Migration Plan

Реализовать change в отдельном worktree от актуальной dev. После unit/static/command checks и записанного portal evidence обновить README/development command catalogue, verify, архивировать change и обновить двустороннюю issue trace. Доставка — PR в dev; release 0.2.0 отдельным процессом. При откате кода удалить семь registrations/routes вместе с документацией; откат CLI не отменяет уже выполненные действия на портале.

## Reconciliation after task:my delivery — 2026-10-14

Issue #118 / merged PR #122 adds task:my independently of lifecycle. The active registration delta is rebased to the canonical 42-command block and preserves the lifecycle coverage scenario. task-mvp-scope records 17 remaining post-MVP exclusions and 42 current task commands; the historical 0.1.0 exclusion list is unchanged. Canonical normalized statements from #114 remain intact so future lifecycle sync cannot regress the inventory or strict validation. No lifecycle runtime or authorization behavior changes in this reconciliation.
