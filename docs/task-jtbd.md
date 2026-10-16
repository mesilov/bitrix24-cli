# JTBD задач по ролям

Сверка каталога для [#100](https://github.com/mesilov/bitrix24-cli/issues/100) от 14.10.2026, базовый commit `8a4bd2ab0604d905fcf091e0f98ff282380080f4`. Дополнено для [#118](https://github.com/mesilov/bitrix24-cli/issues/118) от 14.10.2026 на базе `8662ee7` (новая команда поверх этого снимка). Дополнено для [#58](https://github.com/mesilov/bitrix24-cli/issues/58) от 14.10.2026: исходники реализации `3653131e27b0767604c6e64f7ae7b72a5f23e369`, база `dev` — `38a39a03ba8cbffaec45cc9b2a289c32ccd97231`. Задача исходной документации: [#80](https://github.com/mesilov/bitrix24-cli/issues/80).

[Карта публичных CLI-вызовов](cli-command-map.md) содержит назначения и проверяемые
примеры команд. Для любого сценария можно открыть `b24cli COMMAND --help`;
`b24cli --help` показывает обзор приложения, а `--about` — версию и сведения о нём.
Изменение справки не расширяет реализацию JTBD и не подтверждает live-приёмку.

Сверка [#149](https://github.com/mesilov/bitrix24-cli/issues/149) от 15.10.2026: перед реальной REST-командой CLI проверяет scope выбранного плана с кешем на сутки. Подтверждённая нехватка прав даёт `scope-missing` / exit 4 только затронутой операции: отсутствие `im` блокирует чтение/изменение/удаление сообщений чата, а `task:list` продолжает работать. `connection:check` принудительно обновляет права; порядок исправления и условные зависимости описаны в [проверке разрешений](connection-scopes.md). [Unit-тесты](../tests/Unit/ScopeValidationCommandTest.php) проверяют изоляцию команд и human/plain/JSON диагностику через MockHttpClient. [Интеграционный тест](../tests/Integration/Connection/CheckConnectionCommandTest.php) пропущен без webhook; живая приёмка нового поведения не подтверждена. Состав и признаки успеха 22 JTBD сохранены.

Сверка [#136](https://github.com/mesilov/bitrix24-cli/issues/136) от 14.10.2026: runtime SHA `f8c380a4539d78556c97655f122a9ba52c1651c8`, база `dev` — `5330662`. Для PM-02/AM-03 длительности представлены в seconds/hours/days с исключением субботы и воскресенья по серверному offset; приёмка #121 подтверждена и завершена. Число сценариев и их ID сохранены.

**25 сценариев:** 9 для руководителя отдела, 7 для аккаунт-менеджера проекта, 9 для рядового исполнителя. Исходные 22 сценария: ситуация, работа, цель и признак успеха взяты из [канонической спецификации task-workflows](../openspec/specs/task-workflows/spec.md); ID сохранены для трассировки на команды и тесты. Новый PM-09 пока задан в delta [add-weekly-task-report](../openspec/changes/add-weekly-task-report/specs/task-workflows/spec.md) и имеет статус «План».

Группировка «руководитель отдела» соответствует исходной роли «Руководитель проекта» (`PM-*`), «аккаунт-менеджер проекта» — «Аккаунт менеджер» (`AM-*`), «рядовой исполнитель» — «Сотрудник» (`EMP-*`). В группе PM приведены согласованные сценарии управления проектной работой; отдельные сценарии управления отделом пока не специфицированы. Эта группировка не меняет исходные требования и не предоставляет прав в Битрикс24: действия зависят от текущего подключения.

Сверка [#72](https://github.com/mesilov/bitrix24-cli/issues/72) от 16.10.2026, база `dev` — `536131d9dbeb4a5c9e189f20e604e8c26c812e03`: добавлен PM-08 из [спецификации task-templates](../openspec/specs/task-templates/spec.md). Статусы ниже означают наличие команд; live-приёмка шаблонов не подтверждена.

Сверка [#162](https://github.com/mesilov/bitrix24-cli/issues/162) от 16.10.2026, база `dev` — `6eb4673e16ca21fcd242c3f9b1b987dc82716752`: добавлен EMP-09; PM-04 дополнен передачей между двумя сотрудниками через `task:handover --from`. Команда реализована; live-приёмка новой передачи не подтверждена. [Руководство передачи](task-handover.md).

Сверка [#165](https://github.com/mesilov/bitrix24-cli/issues/165) от 16.10.2026, база `dev` — `2e83e8b634baab3fd2bbeec6ec51c3cdbf20f403`: EMP-09/PM-04 поддерживают фильтр исходного статуса, исключения ID и пять целей, включая завершение/контроль. Итог назначения и перехода проверяется отдельно. Состав каталога сохранён: 24 JTBD, 49 task-команд, 63 публичных команды. Новые unit-контракты и opt-in integration cases не подтверждают live-приёмку.

Сверка [#170](https://github.com/mesilov/bitrix24-cli/issues/170) от 16.10.2026, база dev `0caf9db`: проверка состава EMP-09/PM-04 использует фактический scope `sonet_group`. Серверный сброс статуса в `pending` после назначения обрабатывается достижением явной цели либо восстановлением исходного статуса штатными действиями. Повторная live-матрица: 18/18 проверок PASS; 4/4 integration tests PASS, включая ожидаемые partial/отказы. [Evidence и ограничения](../openspec/changes/archive/2026-10-16-fix-task-handover-project-scope/live-acceptance.md). Существующие 24 JTBD и ID сохранены.

Сверка планирования от 16.10.2026, база `dev` — `0caf9db62fce3f91852aafc77fd9b25cb3f9bde2`: добавлен PM-09 «Недельный отчёт». По умолчанию выбранный проект целиком, optional `--responsible` сужает все четыре показателя. Команда `task:report` не реализована; каталог сохраняет 49 task-команд / 63 публичных. [Постановка и состояние GitHub issue](../openspec/changes/add-weekly-task-report/proposal.md#github-issues).

## Роли

- **[Руководитель отдела](#role-manager)** — организует работу команды: планирует этапы, следит за рисками, снимает препятствия, пересогласует сроки и распределение работ, принимает результат внутри команды. Сценарии `PM-01`…`PM-09` описывают управление проектной работой и её координацию, включая [недельный отчёт](#pm-09).
- **[Аккаунт-менеджер проекта](#role-account-manager)** — связывает клиента и команду: передаёт запросы, следит за обещаниями, сообщает о ходе работ, согласует изменения требований и передаёт результат с обратной связью клиента. Сценарии `AM-01`…`AM-07`.
- **[Рядовой исполнитель](#role-executor)** — выполняет задачи: выбирает следующую работу, получает контекст, запрашивает помощь, учитывает актуальные ожидания, предъявляет результат и фиксирует трудозатраты. Сценарии `EMP-01`…`EMP-09`.


<a id="role-manager"></a>

## Руководитель отдела

<a id="pm-01"></a>

### [PM-01](../openspec/specs/task-workflows/spec.md) — Планирование этапа

- **Когда:** согласован очередной этап проекта.
- **Хочу:** разложить результат на задачи с ответственными, сроками и критериями приёмки.
- **Чтобы:** команда могла приступить к работе без дополнительных догадок.
- **Признак успеха:** У каждой задачи понятны результат, исполнитель, срок и условия завершения.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:add`](../src/Console/Command/Task/AddTaskCommand.php) (`--user-fields` / `--user-fields-file`) | [`AddTaskCommandTest.php`](../tests/Integration/Task/AddTaskCommandTest.php) |
| ✅ Готово | [`task:show`](../src/Console/Command/Task/ShowTaskCommand.php) | [`ShowTaskCommandTest.php`](../tests/Integration/Task/ShowTaskCommandTest.php) |
| ✅ Готово | [`task:checklist:add`](../src/Console/Command/Task/AddTaskChecklistCommand.php) | [`AddTaskChecklistCommandTest.php`](../tests/Integration/Task/AddTaskChecklistCommandTest.php) |
| ✅ Готово | [`task:checklist:item:add`](../src/Console/Command/Task/AddTaskChecklistItemCommand.php) | [`AddTaskChecklistItemCommandTest.php`](../tests/Integration/Task/AddTaskChecklistItemCommandTest.php) |
| 🕓 План | `task:subtask:add` | Нет |

**Границы:** Проверяются отдельные создание/карточка/чек-листы. Создание с `--user-fields`/`--user-fields-file` передаёт обычные и кастомные значения одним REST1 add; ordinary advanced create проходит локальную проверку без editable-метаданных. Portal-приёмка #58 подтверждает string/numeric/multiple значения и отказ при отсутствии обязательного поля на SDK playground; детали и ограничения — ниже. Сквозная декомпозиция этапа и полнота критериев приёмки не проверены.

<a id="pm-02"></a>

### [PM-02](../openspec/specs/task-workflows/spec.md) — Выявление рисков

- **Когда:** начинается рабочий день или приближается контрольная точка.
- **Хочу:** увидеть задачи с риском срыва.
- **Чтобы:** направить внимание туда, где требуется вмешательство.
- **Признак успеха:** Видны просрочки, приближающиеся сроки, препятствия и ожидаемые решения.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:status:durations`](../src/Console/Command/Task/StatusDurationsTaskCommand.php) | [`StatusDurationsTaskCommandTest.php`](../tests/Integration/Task/StatusDurationsTaskCommandTest.php) |
| ✅ Готово | [`task:list`](../src/Console/Command/Task/ListTaskCommand.php) | [`ListTaskCommandTest.php`](../tests/Integration/Task/ListTaskCommandTest.php) |
| 🕓 План | `task:plan` | Нет |
| 🕓 План | `task:risks` | Нет |
| 🕓 План | `task:dependency:list` | Нет |

**Границы:** Для status:durations offline-арифметика и live partial-result проверены; отдельный SDK-сценарий подтвердил возврат с контроля и повторное принятие, код 4 означает supposedly_completed; граница текущей жизни проверяется по REST3 list.created, старые события сохранены отдельно, положительные STATUS 2/3/4/5 подтверждены. Обычный CLI проверяет точные суммы с двумя повторными открытиями и длинным циклом; REST1 next exhaustion/optional total подтверждены официальным протоколом. Race/page/budget/counter нарушения остаются partial. Для #136 totals каждого статуса содержат seconds (целые), hours/days (до двух знаков), без precision; день равен 24 часам. Суббота и воскресенье исключены по offset серверного created, исходные даты сохранены. Формат подтверждён 3 live-тестами; длинная удалённая задача 6372 проверена воспроизведением сохранённых API ответов, а не новым запросом к ней.  Проверяются ID, своя роль, groupId, responsibleId и AND с логическими статусами. На портале проверены проект 234 и исполнитель 14. supposedly_completed означает ожидание контроля, completed — завершение. --all снимает только лимит вывода; неполный обход не доказывает полноту проекта. Сквозная оценка рисков/препятствий/зависимостей отдельно не проверена.

<a id="pm-03"></a>

### [PM-03](../openspec/specs/task-workflows/spec.md) — Устранение препятствий

- **Когда:** выполнение остановилось из-за зависимости или нехватки информации.
- **Хочу:** установить причину и назначить действие по устранению препятствия.
- **Чтобы:** восстановить движение проекта.
- **Признак успеха:** Понятно, кто и что должен сделать для продолжения работы.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | [`SendTaskChatMessageCommandTest.php`](../tests/Integration/Task/SendTaskChatMessageCommandTest.php) |
| 🕓 План | `task:blocker:raise` | Нет |
| 🕓 План | `task:blocker:resolve` | Нет |

**Границы:** Проверяется сохранение сообщения в нужном чате; назначения действия, состояния препятствия и его устранения нет.

<a id="pm-04"></a>

### [PM-04](../openspec/specs/task-workflows/spec.md) — Пересогласование плана

- **Когда:** меняются приоритеты или доступность исполнителей.
- **Хочу:** пересогласовать распределение работ и сроки.
- **Чтобы:** сохранить выполнимый план.
- **Признак успеха:** Изменения зафиксированы, затронутые участники понимают новые договорённости.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:assign`](../src/Console/Command/Task/AssignTaskCommand.php) | [`AssignTaskCommandTest.php`](../tests/Integration/Task/AssignTaskCommandTest.php) |
| ✅ Готово | [`task:handover`](../src/Console/Command/Task/HandoverTasksCommand.php) (`--group-id`, `--from`, `--responsible`, `--source-status`, `--exclude-id`, `--target-status`) | [`HandoverTasksCommandTest.php`](../tests/Integration/Task/HandoverTasksCommandTest.php) |
| ✅ Готово | [`task:deadline:set`](../src/Console/Command/Task/SetTaskDeadlineCommand.php) | [`SetTaskDeadlineCommandTest.php`](../tests/Integration/Task/SetTaskDeadlineCommandTest.php) |
| 🕓 План | `task:plan` | Нет |
| 🕓 План | `task:change:propose` | Нет |
| 🕓 План | `task:change:accept` | Нет |

**Границы:** Ответственный проверяется на текущем пользователе, срок — отдельно; передача другому пользователю через task:assign (#42) и согласование плана не доказаны; приёмка task:handover описана отдельно ниже.

**Передача между сотрудниками (PM-04):** руководитель задаёт исходного сотрудника через `--from` и получателя через `--responsible`. Команда передаёт выбранные доступные незавершённые задачи первого в выбранном проекте второму. Исходный статус и исключения ID ограничивают набор; явный целевой статус задаётся штатными действиями. Проверяются активное членство обоих, полнота выбора, назначение и итоговый статус. Права подключения не расширяются; при ошибке последующие записи прекращаются. Сроки сохраняются; без целевого статуса проверяется сохранение исходного. Завершение и контроль требуют прав и обязательного native result. Live-матрица #170 подтвердила manager mode, фильтр/исключения, все пять целей и expected partial; подробные ограничения и права подключения указаны в evidence.

<a id="pm-05"></a>

### [PM-05](../openspec/specs/task-workflows/spec.md) — Внутренняя приёмка

- **Когда:** исполнитель предъявляет результат.
- **Хочу:** проверить его по условиям приёмки и принять либо вернуть с конкретными замечаниями.
- **Чтобы:** завершённая задача означала полученный результат.
- **Признак успеха:** Зафиксировано решение; при возврате понятны необходимые исправления.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:result:list`](../src/Console/Command/Task/ListTaskResultsCommand.php) | [`ListTaskResultsCommandTest.php`](../tests/Integration/Task/ListTaskResultsCommandTest.php) |
| ✅ Готово | [`task:approve`](../src/Console/Command/Task/ApproveTaskCommand.php) | [`ApproveTaskCommandTest.php`](../tests/Integration/Task/ApproveTaskCommandTest.php) |
| ✅ Готово | [`task:disapprove`](../src/Console/Command/Task/DisapproveTaskCommand.php) | [`DisapproveTaskCommandTest.php`](../tests/Integration/Task/DisapproveTaskCommandTest.php) |
| 🕓 План | `task:acceptance:record` | Нет |

**Границы:** Чтение результатов доступно и имеет командный тест; approve/disapprove подтверждены на собственной задаче с контролем постановщика. Denied-role матрица требует ограниченного webhook; сквозная бизнес-приёмка отдельно не доказана.

<a id="pm-06"></a>

### [PM-06](../openspec/specs/task-workflows/spec.md) — Переписка для координации

- **Когда:** нужно уточнить решение, согласовать действия или снять препятствие по задаче.
- **Хочу:** прочитать историю чата задачи и обсудить следующий шаг с её участниками.
- **Чтобы:** решение и договорённости оставались в контексте задачи.
- **Признак успеха:** Доступна история переписки; сообщение руководителя сохранено в чате нужной задачи и доступно участникам с правами доступа.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:chat:list`](../src/Console/Command/Task/ListTaskChatCommand.php) | [`ListTaskChatCommandTest.php`](../tests/Integration/Task/ListTaskChatCommandTest.php) |
| ✅ Готово | [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | [`SendTaskChatMessageCommandTest.php`](../tests/Integration/Task/SendTaskChatMessageCommandTest.php) |

**Границы:** Проверяются чтение и отправка в чате задачи; denied-role матрица остаётся в #42.


<a id="pm-07"></a>

### [PM-07](../openspec/specs/task-workflows/spec.md) — Чтение результатов задачи

- **Когда:** руководитель готовится проверить работу исполнителя.
- **Хочу:** `task:result:list` показывает результаты с id, текстом, автором, датой, статусом и доступными материалами.
- **Признак успеха:** каждый результат связан с выбранной задачей и пригоден для проверки; решение о приёмке остаётся отдельным действием.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:result:list`](../src/Console/Command/Task/ListTaskResultsCommand.php) | [`ListTaskResultsCommandTest.php`](../tests/Integration/Task/ListTaskResultsCommandTest.php) |

**Границы:** Проверяется работа с native результатами; решение о внутренней приёмке остаётся отдельным процессом.

<a id="pm-08"></a>

### [PM-08](../openspec/specs/task-templates/spec.md) — Сопровождение шаблонов типовых задач

- **Когда:** команда регулярно выполняет однотипные задачи.
- **Хочу:** читать, создавать и обновлять шаблоны, запрашивать удаление устаревших.
- **Чтобы:** поддерживать согласованные настройки типовой работы.
- **Признак успеха:** нужный шаблон доступен по ID, создание/изменение подтверждено; неоднозначный ответ удаления показан явно.

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:template:show`](../src/Console/Command/Task/ShowTaskTemplateCommand.php) | [`ShowTaskTemplateCommandTest.php`](../tests/Integration/Task/ShowTaskTemplateCommandTest.php) |
| ✅ Готово | [`task:template:fields:list`](../src/Console/Command/Task/ListTaskTemplateFieldsCommand.php) | [`ListTaskTemplateFieldsCommandTest.php`](../tests/Integration/Task/ListTaskTemplateFieldsCommandTest.php) |
| ✅ Готово | [`task:template:add`](../src/Console/Command/Task/AddTaskTemplateCommand.php) | [`AddTaskTemplateCommandTest.php`](../tests/Integration/Task/AddTaskTemplateCommandTest.php) |
| ✅ Готово | [`task:template:update`](../src/Console/Command/Task/UpdateTaskTemplateCommand.php) | [`UpdateTaskTemplateCommandTest.php`](../tests/Integration/Task/UpdateTaskTemplateCommandTest.php) |
| ✅ Готово | [`task:template:delete`](../src/Console/Command/Task/DeleteTaskTemplateCommand.php) | [`DeleteTaskTemplateCommandTest.php`](../tests/Integration/Task/DeleteTaskTemplateCommandTest.php) |

**Границы:** операции REST 1.0 зависят от прав подключения и недоступны в strict-rest3. Delete=false даёт exit 3 и confirmed=false; последующее отсутствие видимости не доказывает удаление. Пять отдельных integration tests подготовлены; наличие файлов тестов не подтверждает live-приёмку. Список шаблонов, запуск задачи из шаблона, расписания и чек-листы не входят в поставку. [Ввод полей и форматы](task-usage.md#шаблоны-задач).

<a id="pm-09"></a>

### [PM-09](../openspec/changes/add-weekly-task-report/specs/task-workflows/spec.md) — Недельный отчёт

- **Когда:** подвожу итоги недели выбранного проекта.
- **Хочу:** получить список закрытых задач, список задач на контроле, количество новых задач и количество задач в работе.
- **Чтобы:** оценить выполненную работу, очередь приёмки и текущую нагрузку.
- **Признак успеха:** четыре показателя проверяемы; указаны период, timezone, область доступа и полнота. По умолчанию весь выбранный проект; фильтр конкретного ответственного сужает все четыре показателя.

| Статус | Команда | Тест |
| --- | --- | --- |
| 🕓 План | `task:report --group-id ID [--responsible ID] [--week this-week\|last-week\|YYYY-MM-DD] [--timezone IANA]` | Нет; будущие проверки перечислены в [tasks.md](../openspec/changes/add-weekly-task-report/tasks.md) |

**Состав:** закрытые имеют текущий completed и фактическую closedDate в выбранной неделе; новые считаются по createdDate за неделю. Контроль — текущий supposedly_completed, работа — текущее количество in_progress независимо от недели создания. Неделя с понедельника, по умолчанию last-week; --responsible означает текущего ответственного, а не автора задачи.

**Границы:** это постановка для 0.3.0, команда отсутствует в runtime. Два текущих показателя относятся к чтению при формировании отчёта, даже для прошлой недели; исторический/атомарный snapshot не обещается. Повторно открытая задача не входит в закрытые. Доступная выборка отделена от всего проекта; partial требует неизвестного точного count и exit 3. Подробности — в [спецификации отчёта](../openspec/changes/add-weekly-task-report/specs/task-weekly-report/spec.md). Unit/integration/live-приёмка команды ещё не выполнена.

<a id="role-account-manager"></a>

## Аккаунт-менеджер проекта

<a id="am-01"></a>

### [AM-01](../openspec/specs/task-workflows/spec.md) — Передача запроса клиента

- **Когда:** клиент сообщает новую потребность.
- **Хочу:** превратить обращение в понятную внутреннюю задачу с контекстом и ожидаемым результатом.
- **Чтобы:** запрос не потерялся при передаче команде.
- **Признак успеха:** Сохранены источник запроса, клиентский контекст и ожидаемый результат.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:add`](../src/Console/Command/Task/AddTaskCommand.php) (`--user-fields` / `--user-fields-file`) | [`AddTaskCommandTest.php`](../tests/Integration/Task/AddTaskCommandTest.php) |
| ✅ Готово | [`task:show`](../src/Console/Command/Task/ShowTaskCommand.php) | [`ShowTaskCommandTest.php`](../tests/Integration/Task/ShowTaskCommandTest.php) |
| ✅ Готово | [`task:find`](../src/Console/Command/Task/FindTaskCommand.php) | [`FindTaskCommandTest.php`](../tests/Integration/Task/FindTaskCommandTest.php) |

**Границы:** Проверяются поля создания, карточка и поиск; `--user-fields`/`--user-fields-file` сохраняет поддержанные кастомные значения при создании. Portal-приёмка #58 подтверждает string/numeric/multiple поля SDK playground; портал автора и UF_QUERY не проверены. Сохранение всего клиентского контекста как бизнес-контракта отдельно не проверяется.

<a id="am-02"></a>

### [AM-02](../openspec/specs/task-workflows/spec.md) — Контроль обещаний клиенту

- **Когда:** клиенту обещан результат к определённой дате.
- **Хочу:** отслеживать выполнение связанных задач.
- **Чтобы:** заранее заметить угрозу обещанию.
- **Признак успеха:** Можно определить, какие обязательства выполняются, а какие требуют пересогласования.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:list`](../src/Console/Command/Task/ListTaskCommand.php) | [`ListTaskCommandTest.php`](../tests/Integration/Task/ListTaskCommandTest.php) |
| 🕓 План | `task:risks` | Нет |
| 🕓 План | `task:report` | Нет |
| 🕓 План | `task:context:set` | Нет |

**Границы:** Выборка другого исполнителя через --responsible ID со статусом и необязательным --group-id проверена на портале (userId=14/groupId=234). Учитывается только доступная подключению выборка; risks/report/context остаются планом, клиентские обязательства отдельно не доказаны.

<a id="am-03"></a>

### [AM-03](../openspec/specs/task-workflows/spec.md) — Ответ о ходе работ

- **Когда:** клиент спрашивает о ходе работ.
- **Хочу:** собрать актуальный статус, препятствия и следующий шаг.
- **Чтобы:** дать обоснованный ответ без опроса каждого исполнителя.
- **Признак успеха:** Есть проверяемая сводка: сделано, осталось, что мешает, когда следующее обновление.

Планируемый недельный срез с четырьмя показателями определён в [PM-09](#pm-09). Он не подтверждает готовность всей клиентской сводки AM-03.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:status:durations`](../src/Console/Command/Task/StatusDurationsTaskCommand.php) | [`StatusDurationsTaskCommandTest.php`](../tests/Integration/Task/StatusDurationsTaskCommandTest.php) |
| 🕓 План | `task:report` | Нет |
| 🕓 План | `task:brief` | Нет |

**Границы:** Для status:durations offline-арифметика и live partial-result проверены; отдельный SDK-сценарий подтвердил возврат с контроля и повторное принятие, код 4 означает supposedly_completed; граница текущей жизни проверяется по REST3 list.created, старые события сохранены отдельно, положительные STATUS 2/3/4/5 подтверждены. Обычный CLI проверяет точные суммы с двумя повторными открытиями и длинным циклом; REST1 next exhaustion/optional total подтверждены официальным протоколом. Race/page/budget/counter нарушения остаются partial. Для #136 totals каждого статуса содержат seconds (целые), hours/days (до двух знаков), без precision; день равен 24 часам. Суббота и воскресенье исключены по offset серверного created, исходные даты сохранены. Формат подтверждён 3 live-тестами; длинная удалённая задача 6372 проверена воспроизведением сохранённых API ответов, а не новым запросом к ней.  Календарные интервалы помогают объяснять ход работ; полная клиентская сводка report/brief остаётся планом.

<a id="am-04"></a>

### [AM-04](../openspec/specs/task-workflows/spec.md) — Изменение клиентских требований

- **Когда:** клиент меняет требования или приоритет.
- **Хочу:** зафиксировать изменение и согласовать его влияние с руководителем проекта.
- **Чтобы:** команда работала по актуальным договорённостям.
- **Признак успеха:** Изменения и согласованные последствия для объёма и сроков прослеживаются.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:update`](../src/Console/Command/Task/UpdateTaskCommand.php) (`--user-fields` / `--user-fields-file`) | [`UpdateTaskCommandTest.php`](../tests/Integration/Task/UpdateTaskCommandTest.php) |
| 🕓 План | `task:change:propose` | Нет |
| 🕓 План | `task:change:accept` | Нет |

**Границы:** Проверяется изменение заголовка/описания и кастомных значений через `--user-fields`/`--user-fields-file`: custom-only REST1 update, смешанный REST3 затем REST1, очистка и независимый readback. Portal-приёмка #58 включает unknown/strict-rest3/dry-run без записей; denied-role/unknown outcome/cancellation проверены fixtures, ограниченная роль на портале не проверена. Согласование влияния на объём и сроки не проверяется.

<a id="am-05"></a>

### [AM-05](../openspec/specs/task-workflows/spec.md) — Передача результата клиенту

- **Когда:** команда подготовила результат.
- **Хочу:** получить материалы для передачи клиенту и зафиксировать его обратную связь.
- **Чтобы:** довести договорённость до принятия результата.
- **Признак успеха:** Видно, что передано клиенту, что принято и какие замечания остались; это отдельное событие от внутренней приёмки PM-05.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:result:list`](../src/Console/Command/Task/ListTaskResultsCommand.php) | [`ListTaskResultsCommandTest.php`](../tests/Integration/Task/ListTaskResultsCommandTest.php) |
| 🕓 План | `task:delivery:record` | Нет |
| 🕓 План | `task:feedback:record` | Нет |
| 🕓 План | `task:acceptance:record` | Нет |
| 🕓 План | `task:report` | Нет |

**Границы:** Чтение результатов доступно и имеет командный тест; передача клиенту и его приёмка не автоматизированы.

<a id="am-06"></a>

### [AM-06](../openspec/specs/task-workflows/spec.md) — Переписка по клиентским договорённостям

- **Когда:** нужно передать уточнение клиента или получить ответ команды по задаче.
- **Хочу:** прочитать обсуждение и отправить вопрос или уточнение в чат задачи.
- **Чтобы:** сохранить единый контекст клиентских договорённостей при передаче команде.
- **Признак успеха:** Уточнение и ответы прослеживаются в чате задачи; внешнему клиенту доступ автоматически не предоставляется.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:chat:list`](../src/Console/Command/Task/ListTaskChatCommand.php) | [`ListTaskChatCommandTest.php`](../tests/Integration/Task/ListTaskChatCommandTest.php) |
| ✅ Готово | [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | [`SendTaskChatMessageCommandTest.php`](../tests/Integration/Task/SendTaskChatMessageCommandTest.php) |

**Границы:** Проверяются сообщения чата задачи; клиентское согласование и доступ внешнего клиента не проверяются, denied-role — #42.


<a id="am-07"></a>

### [AM-07](../openspec/specs/task-workflows/spec.md) — Получение результатов задачи

- **Когда:** команда подготовила результат по клиентскому запросу.
- **Хочу:** менеджер получает сохранённый текст результата и доступные API сведения через `task:result:list`.
- **Признак успеха:** понятны предъявленный результат, задача и автор; отправка клиенту и его принятие фиксируются отдельным процессом.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:result:list`](../src/Console/Command/Task/ListTaskResultsCommand.php) | [`ListTaskResultsCommandTest.php`](../tests/Integration/Task/ListTaskResultsCommandTest.php) |

**Границы:** Проверяется чтение результатов задачи; передача внешнему клиенту и его приёмка не автоматизированы.

<a id="role-executor"></a>

## Рядовой исполнитель

<a id="emp-01"></a>

### [EMP-01](../openspec/specs/task-workflows/spec.md) — Выбор следующей работы

- **Когда:** планирую рабочий день.
- **Хочу:** увидеть свои актуальные задачи, сроки и согласованные приоритеты.
- **Чтобы:** выбрать следующую работу.
- **Признак успеха:** Понятно, что делать сейчас и какие обязательства требуют внимания.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:list`](../src/Console/Command/Task/ListTaskCommand.php) | [`ListTaskCommandTest.php`](../tests/Integration/Task/ListTaskCommandTest.php) |
| ✅ Готово | [`task:my`](../src/Console/Command/Task/MyTaskCommand.php) | [`MyTaskCommandTest.php`](../tests/Integration/Task/MyTaskCommandTest.php) |

**Границы:** `task:my --user ID` выбирает доступные задачи явного исполнителя; `--open` и `--due-before` сочетаются через AND. Отдельные SDK fixtures проверяют исполнителя, открытые/завершённые задачи, строгую границу срока, отсутствие дедлайна и scan budget; unit-контракты покрывают все четыре открытых состояния и неполные данные. Права задаются подключением. Статус команды означает реализацию; live evidence хранится в verification change #118 и не доказывает доступность всех задач/прав на любом портале. Автоматическая приоритизация и сквозной выбор очереди работ EMP-01 целиком не проверяются. Свои роли и логические --status фильтры task:list сохранены; проверка членства в проекте после complete/approve/disapprove/renew относится к lifecycle change #60.

<a id="emp-02"></a>

### [EMP-02](../openspec/specs/task-workflows/spec.md) — Получение контекста

- **Когда:** приступаю к задаче.
- **Хочу:** получить требования, материалы и условия приёмки.
- **Чтобы:** выполнить работу с ожидаемым качеством.
- **Признак успеха:** Контекст доступен; недостающие сведения можно запросить в обсуждении задачи.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:show`](../src/Console/Command/Task/ShowTaskCommand.php) | [`ShowTaskCommandTest.php`](../tests/Integration/Task/ShowTaskCommandTest.php) |
| ✅ Готово | [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | [`SendTaskChatMessageCommandTest.php`](../tests/Integration/Task/SendTaskChatMessageCommandTest.php) |
| ✅ Готово | [`task:find`](../src/Console/Command/Task/FindTaskCommand.php) | [`FindTaskCommandTest.php`](../tests/Integration/Task/FindTaskCommandTest.php) |
| ✅ Готово | [`task:checklist:list`](../src/Console/Command/Task/ListTaskChecklistsCommand.php) | [`ListTaskChecklistsCommandTest.php`](../tests/Integration/Task/ListTaskChecklistsCommandTest.php) |
| ✅ Готово | [`task:checklist:item:list`](../src/Console/Command/Task/ListTaskChecklistItemsCommand.php) | [`ListTaskChecklistItemsCommandTest.php`](../tests/Integration/Task/ListTaskChecklistItemsCommandTest.php) |

**Границы:** Проверяются карточка, поиск, чат и чек-листы; полнота требований, материалов и условий приёмки целиком не проверяется.

<a id="emp-03"></a>

### [EMP-03](../openspec/specs/task-workflows/spec.md) — Запрос помощи

- **Когда:** встречаю препятствие.
- **Хочу:** сообщить, что мешает, какая помощь нужна и кто может её предоставить.
- **Чтобы:** получить решение и продолжить работу.
- **Признак успеха:** Препятствие заметно ответственным; указан необходимый следующий шаг.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | [`SendTaskChatMessageCommandTest.php`](../tests/Integration/Task/SendTaskChatMessageCommandTest.php) |
| 🕓 План | `task:blocker:raise` | Нет |

**Границы:** Проверяется отправка сообщения; получение решения и переход препятствия в разрешённое состояние не проверяются.

<a id="emp-04"></a>

### [EMP-04](../openspec/specs/task-workflows/spec.md) — Работа по актуальным ожиданиям

- **Когда:** меняются требования или приходит обратная связь.
- **Хочу:** понять актуальное ожидание и необходимые исправления.
- **Чтобы:** избежать работы по устаревшим договорённостям.
- **Признак успеха:** Понятно, что изменилось и какой результат теперь ожидается.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:show`](../src/Console/Command/Task/ShowTaskCommand.php) | [`ShowTaskCommandTest.php`](../tests/Integration/Task/ShowTaskCommandTest.php) |
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

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:result:add`](../src/Console/Command/Task/AddTaskResultCommand.php) | [`AddTaskResultCommandTest.php`](../tests/Integration/Task/AddTaskResultCommandTest.php) |
| ✅ Готово | [`task:file:attach`](../src/Console/Command/Task/AttachTaskFilesCommand.php) | [`AttachTaskFilesCommandTest.php`](../tests/Integration/Task/AttachTaskFilesCommandTest.php) |
| 🕓 План | `task:result:from-message` | Нет |
| ✅ Готово | [`task:complete`](../src/Console/Command/Task/CompleteTaskCommand.php) | [`CompleteTaskCommandTest.php`](../tests/Integration/Task/CompleteTaskCommandTest.php) |

**Границы:** Проверяются создание native результата и прикрепление существующего/загрузка локального файла. Independent attachment membership — #42; task:complete проверяет обязательный native result и возвращает фактический completed либо supposedly_completed; оба исхода проверены на портале. Denied-role матрица и result:from-message остаются непроверенными/планируемыми.

<a id="emp-06"></a>

### [EMP-06](../openspec/specs/task-workflows/spec.md) — Учёт трудозатрат

- **Когда:** требуется учёт трудозатрат.
- **Хочу:** зафиксировать время и содержание выполненной работы.
- **Чтобы:** отчёт отражал фактические затраты.
- **Признак успеха:** Запись связана с задачей, содержит длительность и пояснение; ошибку можно исправить.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:time:show`](../src/Console/Command/Task/ShowTaskTimeCommand.php) | [`ShowTaskTimeCommandTest.php`](../tests/Integration/Task/ShowTaskTimeCommandTest.php) |
| ✅ Готово | [`task:time:add`](../src/Console/Command/Task/AddTaskTimeEntryCommand.php) | [`AddTaskTimeEntryCommandTest.php`](../tests/Integration/Task/AddTaskTimeEntryCommandTest.php) |
| ✅ Готово | [`task:time:list`](../src/Console/Command/Task/ListTaskTimeEntriesCommand.php) | [`ListTaskTimeEntriesCommandTest.php`](../tests/Integration/Task/ListTaskTimeEntriesCommandTest.php) |
| ✅ Готово | [`task:time:update`](../src/Console/Command/Task/UpdateTaskTimeEntryCommand.php) | [`UpdateTaskTimeEntryCommandTest.php`](../tests/Integration/Task/UpdateTaskTimeEntryCommandTest.php) |
| ✅ Готово | [`task:time:delete`](../src/Console/Command/Task/DeleteTaskTimeEntryCommand.php) | [`DeleteTaskTimeEntryCommandTest.php`](../tests/Integration/Task/DeleteTaskTimeEntryCommandTest.php) |

**Границы:** Проверяются собственные записи и граница полноты elapsedTime; полнота списка, чужие права и wrong-task проверки остаются частью #42.

<a id="emp-07"></a>

### [EMP-07](../openspec/specs/task-workflows/spec.md) — Переписка при выполнении

- **Когда:** нужно задать вопрос, уточнить требования или сообщить о ходе работы.
- **Хочу:** прочитать историю чата задачи и написать её участникам.
- **Чтобы:** получить согласованный ответ и продолжить работу в актуальном контексте.
- **Признак успеха:** Вопрос и ответ сохранены при задаче; участник может продолжить обсуждение, не теряя предыдущих сообщений.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:chat:list`](../src/Console/Command/Task/ListTaskChatCommand.php) | [`ListTaskChatCommandTest.php`](../tests/Integration/Task/ListTaskChatCommandTest.php) |
| ✅ Готово | [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | [`SendTaskChatMessageCommandTest.php`](../tests/Integration/Task/SendTaskChatMessageCommandTest.php) |

**Границы:** Проверяются чтение и отправка в чате задачи; сквозная переписка вопрос/ответ и denied-role матрица не подтверждены (#42).


<a id="emp-08"></a>

### [EMP-08](../openspec/specs/task-workflows/spec.md) — Ведение результатов задачи

- **Когда:** сотрудник завершил часть работы либо обнаружил ошибку в предъявленном результате.
- **Хочу:** он добавляет результат, выбирает его ID из списка, изменяет текст или удаляет результат с подтверждением.
- **Признак успеха:** API подтверждает операцию над результатом этой задачи; статус задачи не меняется CLI автоматически.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:result:add`](../src/Console/Command/Task/AddTaskResultCommand.php) | [`AddTaskResultCommandTest.php`](../tests/Integration/Task/AddTaskResultCommandTest.php) |
| ✅ Готово | [`task:result:list`](../src/Console/Command/Task/ListTaskResultsCommand.php) | [`ListTaskResultsCommandTest.php`](../tests/Integration/Task/ListTaskResultsCommandTest.php) |
| ✅ Готово | [`task:result:update`](../src/Console/Command/Task/UpdateTaskResultCommand.php) | [`UpdateTaskResultCommandTest.php`](../tests/Integration/Task/UpdateTaskResultCommandTest.php) |
| ✅ Готово | [`task:result:delete`](../src/Console/Command/Task/DeleteTaskResultCommand.php) | [`DeleteTaskResultCommandTest.php`](../tests/Integration/Task/DeleteTaskResultCommandTest.php) |

**Границы:** Проверяются операции над результатом выбранной задачи; завершение задачи и передача на контроль не выполняются автоматически.

<a id="emp-09"></a>

### [EMP-09](../openspec/specs/task-workflows/spec.md) — Передача задач коллеге перед отпуском

- **Когда:** я ухожу в отпуск и у меня остаются незавершённые задачи проекта.
- **Хочу:** одной командой передать выбранные задачи согласованному активному участнику проекта, исключить ID и при необходимости задать целевой статус.
- **Чтобы:** работа продолжалась без моего участия, а сроки и договорённости сохранились.
- **Признак успеха:** новый ответственный и ожидаемый статус подтверждены по каждой задаче; существующие карточки и контекст сохранены; по непереданным задачам видны причины и следующие шаги.

**Команды и интеграционные тесты**

| Статус | Команда | Тест |
| --- | --- | --- |
| ✅ Готово | [`task:handover`](../src/Console/Command/Task/HandoverTasksCommand.php) (`--group-id`, `--responsible`, `--source-status`, `--exclude-id`, `--target-status`, `--dry-run`) | [`HandoverTasksCommandTest.php`](../tests/Integration/Task/HandoverTasksCommandTest.php) |

**Границы:** без `--from` источник — текущий пользователь подключения. Полный bounded scan выбирает открытые задачи проекта с учётом исходного статуса и исключений ID. Перед записью проверяется точный статус снимка; после назначения и переходов — ответственный, проект и ожидаемый статус. При partial scan записей нет. Отказ, конфликт или неизвестный исход останавливает пакет, сохраняя подтверждённые компоненты. Сроки, описание и другие роли не меняются; сообщения и материалы не создаются. REST1 требует `sonet_group` и разрешений lifecycle; strict-rest3 запрещает команду до API. Завершение учитывает native result и контроль, сервер может затронуть подзадачи по своим настройкам. Повторная live-приёмка #170 подтверждена на отдельном проекте; права ограниченного non-admin пользователя и побочные эффекты на подзадачи live не проверялись.

**Выбор и статус (EMP-09):** запрос «отложенные с Юлии на меня, кроме 92888, тоже в отложенные» задаётся `--from ID --responsible ID --source-status deferred --exclude-id 92888 --target-status deferred` вместе с проектом. Цели pending/in_progress/deferred/supposedly_completed/completed выполняются штатными действиями. Без цели исходный статус при необходимости восстанавливается после серверного сброса в pending и проверяется. Назначение и переход имеют отдельные результаты; частичная ошибка останавливает пакет без отката и повторов. [Контракты](../tests/Unit/TaskHandoverSelectionStatusTest.php), [руководство](task-handover.md). Live-матрица #170 подтвердила четыре фильтра, исключения, пять целей, complete → approve и native result; недостижимая цель сохраняет partial. Подробные границы приведены в evidence.

## Проверка и сопровождение

При изменении команды или integration test обновляйте сценарии с её JTBD ID; сохраняйте существующие ID сценариев. Источник продуктовых требований — task-workflows, источник связей — jtbd-coverage, факт реализации — CommandCatalog и классы команд, наличие проверки — исходник теста. Изменение бизнес-требования сначала оформляется через OpenSpec.

Для трассировки из issue или отчёта используйте стабильную ссылку `docs/task-jtbd.md#pm-01` (аналогично для остальных ID). Чтобы найти командный тест, откройте файл по ссылке в таблице; пример адресного запуска в окружении проекта:

```sh
make test-integration TEST_ARGS='--group command:task:add'
```

Настройка тестового подключения и правила запуска: [руководство разработки](development.md#тесты-команд). Такой запуск может изменять данные тестового портала; без подключения suite пропускает live-проверки. Он не выполняет весь PM-01/AM-01. Структурная проверка соответствия всех команд их integration classes: [IntegrationCommandCoverageTest](../tests/Unit/IntegrationCommandCoverageTest.php).

## Как читать трассировку

- **Текущие команды** — связи из [jtbd-coverage.json](../openspec/changes/define-task-jtbd/jtbd-coverage.json), существующие в актуальном [CommandCatalog](../src/Bootstrap/CommandCatalog.php); новые PM-07/AM-07/EMP-08 берут команды из требований task-workflows. Ссылки ведут на классы команд.
- **Планируемые команды** — остальные связи исходной матрицы; их нет в текущем runtime. [Каталог кандидатов](../openspec/changes/define-task-jtbd/cli-candidates.md) описывает план, а не доступные команды. Ветки разработки и открытые issues не считаются реализацией в этом снимке.
- **Статус** — «✅ Готово» означает наличие текущей команды в коде; «🕓 План» — команду из исходного плана. Статус не сообщает результат запуска теста.
- **Тесты** — имена существующих файлов интеграционных тестов в `tests/Integration/Task`, с группами PHPUnit `command:<имя команды>` / `happy-path`. Они проверяют команды по отдельности; отдельных сквозных интеграционных тестов с ID `PM-*`, `AM-*`, `EMP-*` сейчас нет. Повторение файла в нескольких сценариях означает общую команду, а не независимое покрытие каждого JTBD.
- Поле **границы** показывает, чего командные тесты не доказывают. Ссылка на тест означает наличие его исходника, а не успешный запуск. Открытая [portal-приёмка #42](https://github.com/mesilov/bitrix24-cli/issues/42) и [границы релиза](rc-0.1.0.md) сохраняются; исходная сверка каталога не запускала live-тесты. Для #58 выполнены 4 CLI portal-теста (63 assertions, без skips), результаты и удаление owned fixtures сохранены в [verification.md](../openspec/changes/archive/2026-10-14-support-task-user-fields-and-fix-advanced-validation/verification.md). Поддержанные string/numeric/multiple поля проверены на SDK playground; UF_QUERY, портал автора, restricted-role live и сквозные бизнес-сценарии не проверены.

## Дополнительная portal-приёмка #42 (14.10.2026)

Передача задачи от пользователя 1 пользователю 16 подтверждена независимым REST3 чтением через второе подключение. Bounded list/title scans возвращают partial/exit 3; схема title совпала для обеих ролей. Известные изменения TITLE видны в history, но navigation остаётся unverified/exit 3. Выбранные companion reads task:show подтверждены отдельно от native populated auditors.

Второе подключение имеет admin=true: denied-role матрица не подтверждена. REST3 fileIds=null после attach ACK не доказывает membership. Контролируемый time-list с тремя записями и nPageSize=2 вернул две записи с complete=true/exit 0: это обнаруженный дефект полноты, а не успешная приёмка. По решению пользователя дальнейшие live проверки остановлены, результат поставляется частично, #42 закрывается без полной приёмки. [Evidence и verification](../openspec/changes/archive/2026-10-14-complete-task-portal-acceptance/verification.md).
