# JTBD задач по ролям

Снимок `dev` от 13.10.2026, исходный commit `96ce5028072f90afe70104660b67e02c528d63de`. Задача документации: [#80](https://github.com/mesilov/bitrix24-cli/issues/80).

**19 существующих сценариев:** 6 для руководителя отдела, 6 для аккаунт-менеджера проекта, 7 для рядового исполнителя. Ситуация, работа, цель и признак успеха взяты из [канонической спецификации task-workflows](../openspec/specs/task-workflows/spec.md); ID сохранены для трассировки на команды и тесты.

Группировка «руководитель отдела» соответствует исходной роли «Руководитель проекта» (`PM-*`), «аккаунт-менеджер проекта» — «Аккаунт менеджер» (`AM-*`), «рядовой исполнитель» — «Сотрудник» (`EMP-*`). В группе PM приведены согласованные сценарии управления проектной работой; отдельные сценарии управления отделом пока не специфицированы. Эта группировка не меняет исходные требования и не предоставляет прав в Битрикс24: действия зависят от текущего подключения.

## Роли

- **[Руководитель отдела](#role-manager)** — организует работу команды: планирует этапы, следит за рисками, снимает препятствия, пересогласует сроки и распределение работ, принимает результат внутри команды. Сценарии `PM-01`…`PM-06` описывают управление проектной работой и её координацию.
- **[Аккаунт-менеджер проекта](#role-account-manager)** — связывает клиента и команду: передаёт запросы, следит за обещаниями, сообщает о ходе работ, согласует изменения требований и передаёт результат с обратной связью клиента. Сценарии `AM-01`…`AM-06`.
- **[Рядовой исполнитель](#role-executor)** — выполняет задачи: выбирает следующую работу, получает контекст, запрашивает помощь, учитывает актуальные ожидания, предъявляет результат и фиксирует трудозатраты. Сценарии `EMP-01`…`EMP-07`.

## Как читать трассировку

- **Текущие команды** — связи из [jtbd-coverage.json](../openspec/changes/define-task-jtbd/jtbd-coverage.json), существующие в [CommandCatalog](../src/Bootstrap/CommandCatalog.php). Ссылки ведут на классы команд.
- **Планируемые команды** — остальные связи исходной матрицы; их нет в текущем runtime. [Каталог кандидатов](../openspec/changes/define-task-jtbd/cli-candidates.md) описывает план, а не доступные команды. Ветки разработки и открытые issues не считаются реализацией в этом снимке.
- **Статус** — «✅ Готово» означает наличие текущей команды в коде; «➖ Нет реализации» — отсутствие текущих mapped команд. «🕓 План» означает команду из исходного плана; «➖ Нет планов в исходном mapping» — отсутствие планируемых команд в этой связи. Статус не сообщает результат запуска теста.
- **Тесты** — существующие идентификаторы `Class::method` в `tests/Integration/Task`, с группами PHPUnit `command:<имя команды>` / `happy-path`. Они проверяют команды по отдельности; отдельных сквозных интеграционных тестов с ID `PM-*`, `AM-*`, `EMP-*` сейчас нет. Повторение метода в нескольких сценариях означает общую команду, а не независимое покрытие каждого JTBD.
- Поле **границы** показывает, чего командные тесты не доказывают. Ссылка на тест означает наличие его исходника, а не успешный запуск. Открытая [portal-приёмка #42](https://github.com/mesilov/bitrix24-cli/issues/42) и [границы релиза](rc-0.1.0.md) сохраняются; в этой задаче live-тесты не запускались.

<a id="role-manager"></a>

## Руководитель отдела

<a id="pm-01"></a>

### [PM-01](../openspec/specs/task-workflows/spec.md) — Планирование этапа

- **Когда:** согласован очередной этап проекта.
- **Хочу:** разложить результат на задачи с ответственными, сроками и критериями приёмки.
- **Чтобы:** команда могла приступить к работе без дополнительных догадок.
- **Признак успеха:** У каждой задачи понятны результат, исполнитель, срок и условия завершения.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:add`](../src/Console/Command/Task/AddTaskCommand.php) | [`AddTaskCommandTest::testHappyPathCreatesTaskWithRequestedFields`](../tests/Integration/Task/AddTaskCommandTest.php) |
| ✅ Готово | [`task:show`](../src/Console/Command/Task/ShowTaskCommand.php) | [`ShowTaskCommandTest::testHappyPathReturnsTaskCard`](../tests/Integration/Task/ShowTaskCommandTest.php) |
| ✅ Готово | [`task:checklist:add`](../src/Console/Command/Task/AddTaskChecklistCommand.php) | [`AddTaskChecklistCommandTest::testHappyPathCreatesChecklistRoot`](../tests/Integration/Task/AddTaskChecklistCommandTest.php) |
| ✅ Готово | [`task:checklist:item:add`](../src/Console/Command/Task/AddTaskChecklistItemCommand.php) | [`AddTaskChecklistItemCommandTest::testHappyPathAddsItemToSelectedChecklist`](../tests/Integration/Task/AddTaskChecklistItemCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:subtask:add` | Нет |

**Границы:** Проверяются отдельные создание/карточка/чек-листы; нет сквозной проверки декомпозиции этапа и полноты критериев приёмки.

<a id="pm-02"></a>

### [PM-02](../openspec/specs/task-workflows/spec.md) — Выявление рисков

- **Когда:** начинается рабочий день или приближается контрольная точка.
- **Хочу:** увидеть задачи с риском срыва.
- **Чтобы:** направить внимание туда, где требуется вмешательство.
- **Признак успеха:** Видны просрочки, приближающиеся сроки, препятствия и ожидаемые решения.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:list`](../src/Console/Command/Task/ListTaskCommand.php) | [`ListTaskCommandTest::testHappyPathListsSelectedTask`](../tests/Integration/Task/ListTaskCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:plan` | Нет |
| 🕓 План | `task:risks` | Нет |
| 🕓 План | `task:dependency:list` | Нет |

**Границы:** Список проверяется по известному ID; оценка рисков, препятствий, зависимостей и полная выборка не проверяются.

<a id="pm-03"></a>

### [PM-03](../openspec/specs/task-workflows/spec.md) — Устранение препятствий

- **Когда:** выполнение остановилось из-за зависимости или нехватки информации.
- **Хочу:** установить причину и назначить действие по устранению препятствия.
- **Чтобы:** восстановить движение проекта.
- **Признак успеха:** Понятно, кто и что должен сделать для продолжения работы.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | [`SendTaskChatMessageCommandTest::testHappyPathSendsMessageToTaskChat`](../tests/Integration/Task/SendTaskChatMessageCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:blocker:raise` | Нет |
| 🕓 План | `task:blocker:resolve` | Нет |

**Границы:** Проверяется сохранение сообщения в нужном чате; назначения действия, состояния препятствия и его устранения нет.

<a id="pm-04"></a>

### [PM-04](../openspec/specs/task-workflows/spec.md) — Пересогласование плана

- **Когда:** меняются приоритеты или доступность исполнителей.
- **Хочу:** пересогласовать распределение работ и сроки.
- **Чтобы:** сохранить выполнимый план.
- **Признак успеха:** Изменения зафиксированы, затронутые участники понимают новые договорённости.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:assign`](../src/Console/Command/Task/AssignTaskCommand.php) | [`AssignTaskCommandTest::testHappyPathSetsResponsibleUser`](../tests/Integration/Task/AssignTaskCommandTest.php) |
| ✅ Готово | [`task:deadline:set`](../src/Console/Command/Task/SetTaskDeadlineCommand.php) | [`SetTaskDeadlineCommandTest::testHappyPathSetsDeadlineWithOffset`](../tests/Integration/Task/SetTaskDeadlineCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:plan` | Нет |
| 🕓 План | `task:change:propose` | Нет |
| 🕓 План | `task:change:accept` | Нет |

**Границы:** Ответственный проверяется на текущем пользователе, срок — отдельно; передача другому пользователю (#42) и согласование плана не доказаны.

<a id="pm-05"></a>

### [PM-05](../openspec/specs/task-workflows/spec.md) — Внутренняя приёмка

- **Когда:** исполнитель предъявляет результат.
- **Хочу:** проверить его по условиям приёмки и принять либо вернуть с конкретными замечаниями.
- **Чтобы:** завершённая задача означала полученный результат.
- **Признак успеха:** Зафиксировано решение; при возврате понятны необходимые исправления.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ➖ Нет реализации | — | Нет |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:result:list` | Нет |
| 🕓 План | `task:approve` | Нет |
| 🕓 План | `task:disapprove` | Нет |
| 🕓 План | `task:acceptance:record` | Нет |

**Границы:** Нет реализованных mapped команд и соответствующих integration tests; внутренняя приёмка не проверена.

<a id="pm-06"></a>

### [PM-06](../openspec/specs/task-workflows/spec.md) — Переписка для координации

- **Когда:** нужно уточнить решение, согласовать действия или снять препятствие по задаче.
- **Хочу:** прочитать историю чата задачи и обсудить следующий шаг с её участниками.
- **Чтобы:** решение и договорённости оставались в контексте задачи.
- **Признак успеха:** Доступна история переписки; сообщение руководителя сохранено в чате нужной задачи и доступно участникам с правами доступа.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:chat:list`](../src/Console/Command/Task/ListTaskChatCommand.php) | [`ListTaskChatCommandTest::testHappyPathListsMessagesOfSelectedTask`](../tests/Integration/Task/ListTaskChatCommandTest.php) |
| ✅ Готово | [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | [`SendTaskChatMessageCommandTest::testHappyPathSendsMessageToTaskChat`](../tests/Integration/Task/SendTaskChatMessageCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| ➖ Нет планов в исходном mapping | — | — |

**Границы:** Проверяются чтение и отправка в чате задачи; denied-role матрица остаётся в #42.


<a id="role-account-manager"></a>

## Аккаунт-менеджер проекта

<a id="am-01"></a>

### [AM-01](../openspec/specs/task-workflows/spec.md) — Передача запроса клиента

- **Когда:** клиент сообщает новую потребность.
- **Хочу:** превратить обращение в понятную внутреннюю задачу с контекстом и ожидаемым результатом.
- **Чтобы:** запрос не потерялся при передаче команде.
- **Признак успеха:** Сохранены источник запроса, клиентский контекст и ожидаемый результат.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:add`](../src/Console/Command/Task/AddTaskCommand.php) | [`AddTaskCommandTest::testHappyPathCreatesTaskWithRequestedFields`](../tests/Integration/Task/AddTaskCommandTest.php) |
| ✅ Готово | [`task:show`](../src/Console/Command/Task/ShowTaskCommand.php) | [`ShowTaskCommandTest::testHappyPathReturnsTaskCard`](../tests/Integration/Task/ShowTaskCommandTest.php) |
| ✅ Готово | [`task:find`](../src/Console/Command/Task/FindTaskCommand.php) | [`FindTaskCommandTest::testHappyPathReturnsTwoUnicodeTitleMatches`](../tests/Integration/Task/FindTaskCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| ➖ Нет планов в исходном mapping | — | — |

**Границы:** Проверяются поля создания, карточка и поиск; сохранение всего клиентского контекста как бизнес-контракта отдельно не проверяется.

<a id="am-02"></a>

### [AM-02](../openspec/specs/task-workflows/spec.md) — Контроль обещаний клиенту

- **Когда:** клиенту обещан результат к определённой дате.
- **Хочу:** отслеживать выполнение связанных задач.
- **Чтобы:** заранее заметить угрозу обещанию.
- **Признак успеха:** Можно определить, какие обязательства выполняются, а какие требуют пересогласования.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ➖ Нет реализации | — | Нет |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:risks` | Нет |
| 🕓 План | `task:report` | Нет |
| 🕓 План | `task:context:set` | Нет |

**Границы:** Нет реализованных mapped команд и соответствующих integration tests; клиентские обязательства и угрозы обещаниям не проверены.

<a id="am-03"></a>

### [AM-03](../openspec/specs/task-workflows/spec.md) — Ответ о ходе работ

- **Когда:** клиент спрашивает о ходе работ.
- **Хочу:** собрать актуальный статус, препятствия и следующий шаг.
- **Чтобы:** дать обоснованный ответ без опроса каждого исполнителя.
- **Признак успеха:** Есть проверяемая сводка: сделано, осталось, что мешает, когда следующее обновление.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ➖ Нет реализации | — | Нет |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:report` | Нет |
| 🕓 План | `task:brief` | Нет |

**Границы:** Нет реализованных mapped команд и соответствующих integration tests; клиентская сводка не проверена.

<a id="am-04"></a>

### [AM-04](../openspec/specs/task-workflows/spec.md) — Изменение клиентских требований

- **Когда:** клиент меняет требования или приоритет.
- **Хочу:** зафиксировать изменение и согласовать его влияние с руководителем проекта.
- **Чтобы:** команда работала по актуальным договорённостям.
- **Признак успеха:** Изменения и согласованные последствия для объёма и сроков прослеживаются.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:update`](../src/Console/Command/Task/UpdateTaskCommand.php) | [`UpdateTaskCommandTest::testHappyPathChangesTitleAndKeepsOmittedDescription`](../tests/Integration/Task/UpdateTaskCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:change:propose` | Нет |
| 🕓 План | `task:change:accept` | Нет |

**Границы:** Проверяется изменение заголовка/описания; согласование влияния на объём и сроки не проверяется.

<a id="am-05"></a>

### [AM-05](../openspec/specs/task-workflows/spec.md) — Передача результата клиенту

- **Когда:** команда подготовила результат.
- **Хочу:** получить материалы для передачи клиенту и зафиксировать его обратную связь.
- **Чтобы:** довести договорённость до принятия результата.
- **Признак успеха:** Видно, что передано клиенту, что принято и какие замечания остались; это отдельное событие от внутренней приёмки PM-05.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ➖ Нет реализации | — | Нет |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:result:list` | Нет |
| 🕓 План | `task:delivery:record` | Нет |
| 🕓 План | `task:feedback:record` | Нет |
| 🕓 План | `task:acceptance:record` | Нет |
| 🕓 План | `task:report` | Нет |

**Границы:** Нет реализованных mapped команд и соответствующих integration tests; передача клиенту и его приёмка не проверены.

<a id="am-06"></a>

### [AM-06](../openspec/specs/task-workflows/spec.md) — Переписка по клиентским договорённостям

- **Когда:** нужно передать уточнение клиента или получить ответ команды по задаче.
- **Хочу:** прочитать обсуждение и отправить вопрос или уточнение в чат задачи.
- **Чтобы:** сохранить единый контекст клиентских договорённостей при передаче команде.
- **Признак успеха:** Уточнение и ответы прослеживаются в чате задачи; внешнему клиенту доступ автоматически не предоставляется.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:chat:list`](../src/Console/Command/Task/ListTaskChatCommand.php) | [`ListTaskChatCommandTest::testHappyPathListsMessagesOfSelectedTask`](../tests/Integration/Task/ListTaskChatCommandTest.php) |
| ✅ Готово | [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | [`SendTaskChatMessageCommandTest::testHappyPathSendsMessageToTaskChat`](../tests/Integration/Task/SendTaskChatMessageCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| ➖ Нет планов в исходном mapping | — | — |

**Границы:** Проверяются сообщения чата задачи; клиентское согласование и доступ внешнего клиента не проверяются, denied-role — #42.


<a id="role-executor"></a>

## Рядовой исполнитель

<a id="emp-01"></a>

### [EMP-01](../openspec/specs/task-workflows/spec.md) — Выбор следующей работы

- **Когда:** планирую рабочий день.
- **Хочу:** увидеть свои актуальные задачи, сроки и согласованные приоритеты.
- **Чтобы:** выбрать следующую работу.
- **Признак успеха:** Понятно, что делать сейчас и какие обязательства требуют внимания.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:list`](../src/Console/Command/Task/ListTaskCommand.php) | [`ListTaskCommandTest::testHappyPathListsSelectedTask`](../tests/Integration/Task/ListTaskCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:my` | Нет |

**Границы:** Список проверяется по известному ID; выбор собственной очереди работ по приоритету целиком не проверяется.

<a id="emp-02"></a>

### [EMP-02](../openspec/specs/task-workflows/spec.md) — Получение контекста

- **Когда:** приступаю к задаче.
- **Хочу:** получить требования, материалы и условия приёмки.
- **Чтобы:** выполнить работу с ожидаемым качеством.
- **Признак успеха:** Контекст доступен; недостающие сведения можно запросить в обсуждении задачи.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:show`](../src/Console/Command/Task/ShowTaskCommand.php) | [`ShowTaskCommandTest::testHappyPathReturnsTaskCard`](../tests/Integration/Task/ShowTaskCommandTest.php) |
| ✅ Готово | [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | [`SendTaskChatMessageCommandTest::testHappyPathSendsMessageToTaskChat`](../tests/Integration/Task/SendTaskChatMessageCommandTest.php) |
| ✅ Готово | [`task:find`](../src/Console/Command/Task/FindTaskCommand.php) | [`FindTaskCommandTest::testHappyPathReturnsTwoUnicodeTitleMatches`](../tests/Integration/Task/FindTaskCommandTest.php) |
| ✅ Готово | [`task:checklist:list`](../src/Console/Command/Task/ListTaskChecklistsCommand.php) | [`ListTaskChecklistsCommandTest::testHappyPathListsOnlyChecklistRoots`](../tests/Integration/Task/ListTaskChecklistsCommandTest.php) |
| ✅ Готово | [`task:checklist:item:list`](../src/Console/Command/Task/ListTaskChecklistItemsCommand.php) | [`ListTaskChecklistItemsCommandTest::testHappyPathListsItemsInsideSelectedChecklist`](../tests/Integration/Task/ListTaskChecklistItemsCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| ➖ Нет планов в исходном mapping | — | — |

**Границы:** Проверяются карточка, поиск, чат и чек-листы; полнота требований, материалов и условий приёмки целиком не проверяется.

<a id="emp-03"></a>

### [EMP-03](../openspec/specs/task-workflows/spec.md) — Запрос помощи

- **Когда:** встречаю препятствие.
- **Хочу:** сообщить, что мешает, какая помощь нужна и кто может её предоставить.
- **Чтобы:** получить решение и продолжить работу.
- **Признак успеха:** Препятствие заметно ответственным; указан необходимый следующий шаг.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | [`SendTaskChatMessageCommandTest::testHappyPathSendsMessageToTaskChat`](../tests/Integration/Task/SendTaskChatMessageCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:blocker:raise` | Нет |

**Границы:** Проверяется отправка сообщения; получение решения и переход препятствия в разрешённое состояние не проверяются.

<a id="emp-04"></a>

### [EMP-04](../openspec/specs/task-workflows/spec.md) — Работа по актуальным ожиданиям

- **Когда:** меняются требования или приходит обратная связь.
- **Хочу:** понять актуальное ожидание и необходимые исправления.
- **Чтобы:** избежать работы по устаревшим договорённостям.
- **Признак успеха:** Понятно, что изменилось и какой результат теперь ожидается.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:show`](../src/Console/Command/Task/ShowTaskCommand.php) | [`ShowTaskCommandTest::testHappyPathReturnsTaskCard`](../tests/Integration/Task/ShowTaskCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:brief` | Нет |
| 🕓 План | `task:change:propose` | Нет |
| 🕓 План | `task:change:accept` | Нет |

**Границы:** Проверяется чтение карточки; обнаружение изменения ожиданий и согласование исправлений не проверяются.

<a id="emp-05"></a>

### [EMP-05](../openspec/specs/task-workflows/spec.md) — Предъявление результата

- **Когда:** работа готова.
- **Хочу:** предъявить результат с подтверждающими материалами и передать его на проверку.
- **Чтобы:** получить решение о приёмке.
- **Признак успеха:** Результат доступен проверяющему; видно, ожидается проверка или нужны исправления.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:file:attach`](../src/Console/Command/Task/AttachTaskFilesCommand.php) | [`AttachTaskFilesCommandTest::testHappyPathConfirmsDiskFileAttachment`](../tests/Integration/Task/AttachTaskFilesCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:result:add` | Нет |
| 🕓 План | `task:result:from-message` | Нет |
| 🕓 План | `task:complete` | Нет |

**Границы:** Проверяется ACK прикрепления существующего файла Диска; independent membership — #42. Native result, завершение и передача на контроль не реализованы.

<a id="emp-06"></a>

### [EMP-06](../openspec/specs/task-workflows/spec.md) — Учёт трудозатрат

- **Когда:** требуется учёт трудозатрат.
- **Хочу:** зафиксировать время и содержание выполненной работы.
- **Чтобы:** отчёт отражал фактические затраты.
- **Признак успеха:** Запись связана с задачей, содержит длительность и пояснение; ошибку можно исправить.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:time:show`](../src/Console/Command/Task/ShowTaskTimeCommand.php) | [`ShowTaskTimeCommandTest::testHappyPathReturnsTimeContextWithItsCompletenessBoundary`](../tests/Integration/Task/ShowTaskTimeCommandTest.php) |
| ✅ Готово | [`task:time:add`](../src/Console/Command/Task/AddTaskTimeEntryCommand.php) | [`AddTaskTimeEntryCommandTest::testHappyPathCreatesOwnTimeEntry`](../tests/Integration/Task/AddTaskTimeEntryCommandTest.php) |
| ✅ Готово | [`task:time:list`](../src/Console/Command/Task/ListTaskTimeEntriesCommand.php) | [`ListTaskTimeEntriesCommandTest::testHappyPathListsOwnTimeEntry`](../tests/Integration/Task/ListTaskTimeEntriesCommandTest.php) |
| ✅ Готово | [`task:time:update`](../src/Console/Command/Task/UpdateTaskTimeEntryCommand.php) | [`UpdateTaskTimeEntryCommandTest::testHappyPathUpdatesSecondsAndKeepsOmittedText`](../tests/Integration/Task/UpdateTaskTimeEntryCommandTest.php) |
| ✅ Готово | [`task:time:delete`](../src/Console/Command/Task/DeleteTaskTimeEntryCommand.php) | [`DeleteTaskTimeEntryCommandTest::testHappyPathDeletesOwnTimeEntry`](../tests/Integration/Task/DeleteTaskTimeEntryCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| ➖ Нет планов в исходном mapping | — | — |

**Границы:** Проверяются собственные записи и граница полноты elapsedTime; полнота списка, чужие права и wrong-task проверки остаются частью #42.

<a id="emp-07"></a>

### [EMP-07](../openspec/specs/task-workflows/spec.md) — Переписка при выполнении

- **Когда:** нужно задать вопрос, уточнить требования или сообщить о ходе работы.
- **Хочу:** прочитать историю чата задачи и написать её участникам.
- **Чтобы:** получить согласованный ответ и продолжить работу в актуальном контексте.
- **Признак успеха:** Вопрос и ответ сохранены при задаче; участник может продолжить обсуждение, не теряя предыдущих сообщений.

**Текущие команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:chat:list`](../src/Console/Command/Task/ListTaskChatCommand.php) | [`ListTaskChatCommandTest::testHappyPathListsMessagesOfSelectedTask`](../tests/Integration/Task/ListTaskChatCommandTest.php) |
| ✅ Готово | [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | [`SendTaskChatMessageCommandTest::testHappyPathSendsMessageToTaskChat`](../tests/Integration/Task/SendTaskChatMessageCommandTest.php) |

**Планируемые команды**

| Статус | Команда | Тест |
| --- | --- | --- |
| ➖ Нет планов в исходном mapping | — | — |

**Границы:** Проверяются чтение и отправка в чате задачи; сквозная переписка вопрос/ответ и denied-role матрица не подтверждены (#42).


## Проверка и сопровождение

При изменении команды или integration test обновляйте сценарии с её JTBD ID; сохраняйте существующие ID сценариев. Источник продуктовых требований — task-workflows, источник связей — jtbd-coverage, факт реализации — CommandCatalog и классы команд, наличие проверки — исходник теста. Изменение бизнес-требования сначала оформляется через OpenSpec.

Для трассировки из issue или отчёта используйте стабильную ссылку `docs/task-jtbd.md#pm-01` (аналогично для остальных ID). Чтобы найти командный тест, используйте указанный `Class::method`; пример адресного запуска в окружении проекта:

```sh
make test-integration TEST_ARGS='--group command:task:add --filter testHappyPathCreatesTaskWithRequestedFields'
```

Настройка тестового подключения и правила запуска: [руководство разработки](development.md#тесты-команд). Такой запуск может изменять данные тестового портала; без подключения suite пропускает live-проверки. Он не выполняет весь PM-01/AM-01. Структурная проверка соответствия всех команд их integration classes: [IntegrationCommandCoverageTest](../tests/Unit/IntegrationCommandCoverageTest.php).
