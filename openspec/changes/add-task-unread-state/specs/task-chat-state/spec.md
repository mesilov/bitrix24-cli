# Spec Delta

## Purpose

Позволить автоматизации получать серверное состояние непрочитанных сообщений чатов задач для пользователя выбранного подключения. Отдельно представлять ручную метку и неизвестные данные, сохраняя различие с упоминаниями и UI-счётчиками.

## ADDED Requirements

### Requirement: Connection scoped server unread predicate
`task:list --unread` SHALL включать задачу тогда и только тогда, когда подтверждённый серверный счётчик её чата положителен для пользователя выбранного подключения. Счётчик SHALL поступать из одного снимка `im.counters.get` за запуск. Флаг SHALL не задавать статус или роль и не выводить факт непрочитанности из отсутствия ответа.

#### Scenario: Unread message without a mention or reply
- **WHEN** снимок содержит `TASKS_TASK["10872"]=1`, задача 6388 связана с этим чатом и проходит остальные фильтры
- **THEN** задача включается с `chatState.counter=1`, даже если сообщение не содержит упоминания

#### Scenario: Read without replying
- **WHEN** пользователь прочитал чат без ответа и в следующем валидном снимке для чата нет положительного счётчика
- **THEN** задача не включается в `--unread`; история ответов не проверяется

#### Scenario: State belongs to the selected connection
- **WHEN** одно подключение имеет счётчик 2, а другое — 0 для той же задачи
- **THEN** каждый запуск использует только состояние фактически выбранного подключения без общего кэша

#### Scenario: System messages and statuses remain explicit
- **WHEN** положительный счётчик вызван системным сообщением либо задача имеет статус completed
- **THEN** флаг сам по себе не исключает задачу; исключение по статусу определяется отдельными критериями
- **AND** результат не называется числом человеческих сообщений или упоминаний

### Requirement: Verified task chat joining with muted counters
CLI SHALL соединять `TASKS_TASK` и `CHAT_MUTED` с доступными задачами по подтверждённому task API `chatId`. `CHAT_MUTED` SHALL учитываться независимо от звука, но чужие типы чатов SHALL не превращаться в задачи. Счётчики одного чата SHALL не складываться при дублировании между разделами.

#### Scenario: Muted task is included
- **WHEN** `CHAT_MUTED["10872"]=3`, task API подтверждает `chatId=10872` у задачи 6388 и остальные критерии выполнены
- **THEN** задача включается с `chatState.counter=3`, независимо от цвета и значения UI-бейджа

#### Scenario: Muted chat is unrelated to tasks
- **WHEN** `CHAT_MUTED` содержит чат, который не связан ни с одной проверенной задачей выбранной выборки
- **THEN** CLI не выводит его как задачу и не читает историю этого чата для поиска связи

#### Scenario: Duplicate counter entry
- **WHEN** оба раздела содержат один chatId с одинаковым счётчиком 2
- **THEN** CLI учитывает значение 2 один раз
- **AND** разные значения для одного chatId означают неизвестное состояние с partial/exit3, а не сумму или произвольный выбор

#### Scenario: Missing association differs from an absent chat
- **WHEN** поле chatId отсутствует или имеет неверный тип и разрешённое чтение выбранной задачи не подтвердило связь
- **THEN** CLI сохраняет известные совпадения, возвращает partial/exit3 и не подставляет счётчик 0
- **AND** явно подтверждённое отсутствие чата исключает задачу из `--unread` без выдуманного chatId

### Requirement: Manual unread marker is independent
В результатах `task:list --unread` CLI SHALL возвращать `chatState.manuallyMarkedUnread` как отдельный служебный boolean из подтверждённого членства chatId в `TASKS_TASK_UNREAD`. Метка SHALL не увеличивать `counter` и не включать задачу без непрочитанных сообщений. Неподтверждённая метка SHALL возвращаться как null с partial/exit3.

#### Scenario: Marker without unread messages
- **WHEN** чат помечен вручную, но его подтверждённый счётчик равен 0
- **THEN** задача не включается в `--unread`

#### Scenario: Marker and unread messages coexist
- **WHEN** задача проходит фильтры, счётчик равен 2 и chatId находится в подтверждённом наборе ручных меток
- **THEN** JSON содержит `chatState.counter=2` и `chatState.manuallyMarkedUnread=true`

#### Scenario: Manual marker source is unavailable
- **WHEN** числовые разделы валидны, задача имеет счётчик 2, но раздел ручных меток отсутствует или не распознан
- **THEN** задача сохраняется с `counter=2`, `manuallyMarkedUnread=null`, `meta.complete=false`, reason `manual-unread-state-unavailable` и exit3

### Requirement: Unsupported counter schemas remain unknown
CLI SHALL проверять поддержку числовых разделов снимка, идентификаторы и целые неотрицательные счётчики до вывода отрицательного результата. Отсутствующий раздел, неподдерживаемая форма или неверный тип SHALL не трактоваться как пустая валидная карта. Имена wire-полей SHALL сохраняться без синонимов и автоматического перехода на другую версию API.

#### Scenario: Supported empty snapshot
- **WHEN** обязательные числовые разделы присутствуют в поддерживаемой форме и не содержат положительных счётчиков
- **THEN** CLI возвращает пустой результат с exit0 и complete=true только при подтверждённых необходимых состояниях

#### Scenario: Task counter section is missing
- **WHEN** ответ содержит CHAT, но не содержит поддерживаемого TASKS_TASK либо CHAT_MUTED
- **THEN** CLI возвращает partial-result/exit3, complete=false и reason `unread-counter-schema-unsupported`
- **AND** ответ не объявляется доказательством отсутствия непрочитанных задач; fallback на COUNTERS, im.recent.list или историю сообщений не выполняется

#### Scenario: Invalid count and identity values
- **WHEN** счётчик имеет boolean, строку, отрицательное значение либо chatId не является положительным целым идентификатором
- **THEN** соответствующее состояние не принимается как 0 или false; результат сообщает неизвестную схему или конфликт и partial/exit3

### Requirement: Unread list completeness and snapshot provenance
Режим SHALL сохранять scope, сортировку, `--max-scan`, `--limit`, `--all`, scanned/matched/returned и limitApplied обычного списка. Полнота SHALL относиться к валидному снимку счётчиков и проверенной выборке задач, а не к атомарному состоянию всего портала. Метаданные SHALL содержать источник, время получения снимка и независимую полноту счётчиков и меток.

#### Scenario: Predicate precedes the output limit
- **WHEN** первые просмотренные задачи прочитаны, а задача с непрочитанными найдена на следующей странице при `--limit 1`
- **THEN** CLI проверяет страницы в пределах бюджета и применяет лимит после всех критериев, включая unread

#### Scenario: Task or counter budget is exhausted
- **WHEN** обход задач либо обработка снимка остановлены по конечному бюджету
- **THEN** известные результаты сохраняются с complete=false, конкретной причиной и exit3; пустой partial не объявляется отсутствием задач
- **AND** `--all` снимает только лимит вывода

#### Scenario: Portal changes after the snapshot
- **WHEN** сообщение прочитано или добавлено после получения снимка, пока CLI читает страницы задач
- **THEN** вывод явно относится ко времени снимка; CLI не обещает атомарность и не обновляет счётчики для каждой строки

### Requirement: Message unread value is preserved
`task:chat:list` SHALL сохранять серверное boolean-поле `unread` каждого возвращённого сообщения без переименования и без вывода из автора, текста или ответов. Отсутствующее или неверно типизированное поле SHALL возвращаться как null с partial/exit3. Состояние окна сообщений SHALL не выдаваться за полный счётчик чата.

#### Scenario: True and false are retained
- **WHEN** `im.dialog.messages.get` возвращает сообщения с `unread=true` и `unread=false`
- **THEN** JSON сохраняет оба значения и все существующие поля сообщений
- **AND** наличие собственного сообщения не превращает прочитанность чужих сообщений в эвристику

#### Scenario: Unread flag was not returned
- **WHEN** у одного сообщения unread отсутствует, равен null либо строке "false"
- **THEN** сообщение сохраняется с `unread=null`, complete=false, reason `message-unread-unavailable` и exit3
- **AND** остальные подтверждённые поля и сообщения сохраняются

#### Scenario: Selected history window
- **WHEN** `--before`, `--after` или `--limit` возвращает окно без unread=true
- **THEN** CLI не утверждает, что весь чат прочитан; существующие scope, pagination и hasMore сохраняются

### Requirement: Observation does not acknowledge reading
Обе команды SHALL только наблюдать серверное состояние. Они SHALL не устанавливать прочитанность, не снимать ручную метку и не менять mute. Чтение сообщений и подсчёт SHALL не отправлять ответ, уведомление или иной запрос записи.

#### Scenario: Repeated inspection
- **WHEN** CLI повторно выполняет `task:list --unread` и `task:chat:list` без внешнего изменения состояния
- **THEN** read position, счётчик, ручная метка и настройки уведомлений не меняются от этих запусков

#### Scenario: Only admitted read routes are used
- **WHEN** выполняется любая из двух команд
- **THEN** план не содержит im.dialog.read, im.dialog.unread, im.recent.unread, im.chat.mute или отправку сообщения
