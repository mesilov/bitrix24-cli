# Spec Delta

## Purpose

Определить явные команды жизненного цикла задачи, условия безопасного выполнения и проверяемый вывод фактического состояния после перехода, включая контроль постановщика и обязательный native result.

## ADDED Requirements

### Requirement: Explicit lifecycle commands
CLI SHALL предоставлять `task:start`, `task:pause`, `task:defer`, `task:renew`, `task:complete`, `task:approve` и `task:disapprove` с единственным положительным позиционным `TASK_ID`. Команды SHALL поддерживать общие connection/output/policy опции и `--dry-run`, работать без диалога подтверждения и не предоставлять произвольный status patch.

#### Scenario: Seven discoverable actions
- **WHEN** пользователь открывает `list task` или help любой lifecycle команды без credentials
- **THEN** доступны все семь имён, назначение, TASK_ID, пример, REST1 ограничение и форматы результата; API не вызывается

#### Scenario: Invalid task reference
- **WHEN** TASK_ID отсутствует, равен нулю, отрицателен, нечисловой или превышает диапазон целого
- **THEN** CLI возвращает usage-error/exit2 до credentials/API; неправильные дополнительные параметры также не разрешают запись

#### Scenario: An explicit action is invoked
- **WHEN** пользователь вызывает `task:pause 123 --no-interaction`
- **THEN** выполняется только выбранный переход задачи 123, без дополнительного вопроса, переключения действия или записи status через update

### Requirement: Version-specific lifecycle admission
Каждое действие SHALL использовать одноимённый `tasks.task.<action>` REST1 метод с `taskId` integer. План SHALL заранее включать REST3 чтения задачи/прав и условное чтение native results для complete. `strict-rest3` SHALL отклонять весь план до credentials/первого запроса. Meta SHALL показывать фактически использованные версии, без fallback или повторов.

#### Scenario: Exact method and parameter
- **WHEN** вызывается любое из семи действий в `task-v3`
- **THEN** mutation отправляет только `taskId` выбранной задачи в соответствующий REST1 метод; `id`, `TASK_ID` и status не подставляются как API параметры

#### Scenario: Strict policy rejects preflight too
- **WHEN** lifecycle команда вызвана с `--api-policy=strict-rest3`, включая `--dry-run`
- **THEN** возвращается policy-denied/exit4, credentials не читаются и никакой REST3 preflight не выполняется

### Requirement: Action-specific permission and semantic preflight
До записи CLI SHALL подтвердить ID задачи, прочитать `status`, `needsControl`, `requireResult` и проверить именно право выбранного действия. Отрицательное право SHALL давать permission-denied/exit1; отсутствующие или повреждённые данные SHALL блокировать запись. `approve`/`disapprove` SHALL требовать `needsControl=true` и `status=supposedly_completed`; сервер SHALL окончательно проверять права и допустимость перехода.

#### Scenario: Edit permission cannot substitute for action permission
- **WHEN** edit=true, но право выбранного lifecycle действия false
- **THEN** запись не вызывается; CLI возвращает permission-denied/exit1 без смены пользователя, команды или политики

#### Scenario: Incomplete preflight
- **WHEN** API не возвращает требуемые поля или boolean право, либо возвращает другую задачу
- **THEN** CLI возвращает семантический gate/exit4 или api-error/exit1 по причине, не считает missing false/true и не выполняет mutation

#### Scenario: Review requires a task awaiting control
- **WHEN** approve/disapprove выбрана для задачи без контроля либо с другим текущим статусом
- **THEN** CLI возвращает lifecycle-unavailable/exit4 до записи; renew не выбирается вместо disapprove

#### Scenario: Race after permitted preflight
- **WHEN** право или статус меняются между чтением и mutation, а сервер отклоняет переход
- **THEN** возвращается серверная ошибка без автоматического повторения или попытки другого lifecycle действия

### Requirement: Required native result before completion
При `requireResult=true` CLI SHALL до complete подтвердить наличие native result именно выбранной задачи через ограниченное REST3 чтение. Отсутствующий или непроверенный result SHALL блокировать переход с exit4. CLI SHALL сохранять `requireResult`, не создавать result/чат/файл неявно и не выдавать один `containsResults` за проверенную запись результата.

#### Scenario: Native result exists
- **WHEN** requireResult=true и чтение native results возвращает запись с положительным id и совпадающим taskId
- **THEN** после остальных проверок допускается один complete; сервер проверяет окончательную допустимость результата, его статус не подменяется статусом задачи

#### Scenario: No native result or unreadable result
- **WHEN** requireResult=true, но результаты пусты, чтение недоступно/повреждено либо принадлежность не подтверждена
- **THEN** возвращается required-result-unverified/exit4 с причиной, mutation не выполняется; обычный чат или вложение не снимают gate

#### Scenario: Result not required
- **WHEN** requireResult=false
- **THEN** result list не вызывается; отсутствие result не блокирует иначе разрешённое complete

### Requirement: Actual task state after lifecycle mutation
Подтверждение SHALL относиться к выбранному taskId. После ACK CLI SHALL читать REST3 карточку и возвращать её `status`, `needsControl` и `requireResult`, без локального вычисления целевого статуса. В JSON SHALL сохраняться data/meta/error envelope с `data.operation` и `data.task`; human/plain SHALL также показывать статус, а не только имя выполненной команды.

#### Scenario: Complete awaits review
- **WHEN** complete подтверждён и чтение возвращает status=supposedly_completed
- **THEN** exit0, confirmed=true и complete=true обозначают подтверждённую команду и полное чтение; human показывает ожидание контроля, JSON/plain сохраняют supposedly_completed, задача не объявляется completed

#### Scenario: Complete is accepted without review
- **WHEN** complete подтверждён и чтение возвращает status=completed
- **THEN** вывод содержит completed; этот статус не выводится только из имени команды или preflight needsControl

#### Scenario: Concurrent state change after the action
- **WHEN** post-read возвращает корректный статус, отличный от ожидаемого для имени действия
- **THEN** CLI показывает полученный статус без его замены или повторного перехода; подтверждение действия не обещает неизменность состояния

### Requirement: Partial and unknown mutation outcomes
CLI SHALL различать неподтверждённую отправленную запись и подтверждённую запись с неполным последующим чтением. Ни один сбой SHALL приводить к повторной mutation. Отмена SHALL сохранять exit130 и остановить дальнейшие запросы. Пользователь SHALL получать безопасный способ самостоятельной сверки через `task:show`.

#### Scenario: Confirmation then failed state read
- **WHEN** mutation вернула корректный task ACK, но post-read отклонён, повреждён или не содержит нужные поля
- **THEN** exit3/partial-result сохраняет confirmed=true, complete=false и причину; неизвестные текущие поля null, preflight состояние не выдаётся за состояние после записи, outcomeUnknown=false

#### Scenario: Response lost or invalid mutation acknowledgement
- **WHEN** отправленный переход не имеет достоверного ACK, включая чужой task.id или malformed response
- **THEN** возвращается exit1 с outcomeUnknown=true и безопасным предложением `task:show TASK_ID`; confirmed=true не выставляется, повторной записи и fallback нет

#### Scenario: Cancellation during or after mutation
- **WHEN** пользователь прерывает отправленную запись либо выполнение перед post-read
- **THEN** exit130 сохраняет доступное подтверждение или outcomeUnknown; новые запросы не выполняются и серверный откат не обещается

### Requirement: Read-only lifecycle preview and server side effects
`--dry-run` SHALL выполнять только разрешённые preflight чтения и показывать действие, задачу, текущий статус и план mutation/post-read без выполнения перехода. Preview SHALL указывать возможное ожидание контроля, обязательный result и настройку `autocompleteSubTasks`; он SHALL отличать предполагаемый исход от подтверждённого. CLI SHALL не изменять эти настройки и не обещать полноту проверки побочных объектов.

#### Scenario: Preview complete with review or subtasks
- **WHEN** complete вызывается с --dry-run и включены needsControl либо autocompleteSubTasks
- **THEN** показываются текущие настройки и возможность серверных побочных эффектов; mutation/post-read не выполняются, завершение основной задачи и подзадач не подтверждается

#### Scenario: Preview cannot bypass semantic gates
- **WHEN** --dry-run используется при запрете права или отсутствующем обязательном результате
- **THEN** возвращается тот же отказ до записи; preview не снимает проверку, а --force не обходит её

### Requirement: Logical task status selection
`task:list` SHALL принимать один точный REST3 `status`: pending, in_progress, supposedly_completed, completed, deferred, declined. Фильтр SHALL сочетаться через AND с responsibleId/groupId и сохранять visible-task scope и scan/limit/partial правила. `--status` и `--where.status` SHALL отклонять остальные значения с usage-error/exit2 до credentials/API, без числовых aliases. Свои задачи SHALL выбираться явным собственным ID, без неявного current-user scope.

#### Scenario: Self, project and other assignee lists
- **WHEN** пользователь выбирает свой responsible ID, group ID или ID другого исполнителя и добавляет --status с документированным строковым значением
- **THEN** task:list возвращает только доступные подключению задачи, соответствующие всем заданным условиям; группу и исполнителя также можно указать вместе

#### Scenario: Review is distinct from completed work
- **WHEN** complete передал задачу на контроль и task:list выбран по supposedly_completed либо completed
- **THEN** первая выборка включает эту задачу, вторая её исключает; фильтрация использует фактический REST3 status без предположения по имени предыдущего действия

#### Scenario: Invalid logical status is not an empty success
- **WHEN** --status либо --where.status содержит опечатку, пустую строку, legacy number/string code или array
- **THEN** usage-error/exit2 возвращается до credentials/API, без молчаливой пустой успешной выборки, OR либо переименования ключа

#### Scenario: Restricted visibility and incomplete scan
- **WHEN** пользователь выбирает другого исполнителя/проект или --all, а доступ ограничен либо max-scan прерывает обход
- **THEN** visible-task scope сохраняется; --all не снимает scan budget, incomplete scan даёт partial/exit3, полнота видимых задач не выдаётся за полный проект или все задачи исполнителя

#### Scenario: Status read consistency after another transition
- **WHEN** задача после approve/disapprove/renew/start/pause/defer читается в карточке и status-filtered списке
- **THEN** membership списка соответствует реально прочитанному status, без локального вычисления цели из action; concurrent изменения оцениваются по моменту чтения

### Requirement: Lifecycle acceptance evidence
Каждая из семи команд SHALL иметь собственный live happy-path test и локальные проверки contract/policy/output. Portal acceptance SHALL отдельно фиксировать контроль, requireResult, роли, переходы и побочные эффекты на собственных fixtures. Пропуск live case или unit success SHALL не считаться портальным подтверждением.

#### Scenario: Control and result acceptance matrix
- **WHEN** выполняется portal acceptance
- **THEN** проверяются complete без контроля и с контролем, approve/disapprove, requireResult с отсутствующим/созданным native result, denied action и autocompleteSubTasks; результаты и недоступные роли/случаи явно записаны
