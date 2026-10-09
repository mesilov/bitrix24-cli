# Проверка потенциального CLI на полноту JTBD

Дата: 2026-10-09. Основания: [основная spec](../../specs/task-workflows/spec.md), [каталог](cli-candidates.md), [JSON проверки](jtbd-coverage.json). Проверка статическая, на уровне проектирования; команд в приложении нет.

Имена команд обновлены по [CLI Guidelines](https://github.com/cli-guidelines/cli-guidelines/tree/697d6a29fc8c93d3981a755c0c7683507ad39c3e): вызов `b24cli` плюс указанное имя команды Symfony Console с двоеточиями. [cli-experience](../../specs/cli-experience/spec.md) задаёт общий UX-контракт. Переименование и удобные flags не меняют API routes, условия покрытия или результаты acceptance.

## Итог

Для исходного полного каталога все **19/19 JTBD имеют кандидатные команды**, но это не 19/19 доказанных сценариев:

- Для новой карточки: **7 сценариев имеют документированные основные маршруты**, **12 условны** из-за рабочих данных, lifecycle semantics, полноты или исключений API.
- Для строго только REST3: **4 имеют документированные основные маршруты**, **14 условны**, **EMP-06 имеет gap записи/исправления времени**.
- Три ролевых chat сценария входят в 7 документированных маршрутов новой карточки, но требуют IM для истории и поэтому условны в strict-rest3.
- Native v3 покрывает card/result/send, а не автоматически согласования, обещания клиенту и устранение препятствий.
- Реализовано/проверено на портале: **0** этих JTBD. Ни one-to-one mapping имени команды, ни наличие SDK wrapper не являются runtime evidence.

Статус documented означает наличие официально описанного основного маршрута с указанными предпосылками. Он не означает успешный acceptance, отсутствие правовых/ресурсных ограничений или готовый алгоритм. Conditional означает явно оставшееся существенное решение; gap — нет документированного требуемого v3 write route.

## Матрица 19 сценариев

| JTBD | Кандидатные команды | Новая карточка | Strict REST3 | Условие / gap |
| --- | --- | --- | --- | --- |
| PM-01 | `task:add`, `task:subtask:add`, `task:show`, `task:checklist:add`, `task:checklist:item:add` | documented | documented | Для каждого задания заданы title, creator/responsible, deadline и критерии в description. Зависимости/чек-листы — отдельные расширения, не обязательны для базовой декомпозиции. |
| PM-02 | `task:list`, `task:plan`, `task:risks`, `task:dependency:list` | conditional | conditional | Просрочки вычисляются; полная выборка локально фильтруется. Для blockers/ожиданий нужен утверждённый источник; v3 gantt.list даёт только исходящие связи. |
| PM-03 | `task:blocker:raise`, `task:blocker:resolve`, `task:chat:send` | conditional | conditional | Нужен контракт событий препятствия, owner/next-step и конфликтных изменений. Чатное уведомление не гарантирует прочтение. |
| PM-04 | `task:plan`, `task:assign`, `task:deadline:set`, `task:change:propose`, `task:change:accept` | conditional | conditional | Само update v3 документировано; согласование и ресурсные ограничения требуют данных/протокола. Ответственный не равен процедуре delegate. |
| PM-05 | `task:result:list`, `task:approve`, `task:disapprove`, `task:acceptance:record` | conditional | conditional | REST3 status writable в модели, но эквивалентность approve/disapprove требует acceptance. До него специальные команды disabled. |
| PM-06 | `task:chat:list`, `task:chat:send` | documented | conditional | Отправка REST3, история через документированный IM companion route; только участник task chat с правами. |
| AM-01 | `task:add`, `task:show`, `task:find` | documented | documented | Для базового сценария источник запроса, клиентский контекст и ожидание вводятся оператором в description. Автоматический импорт из CRM не требуется и не обещается. |
| AM-02 | `task:risks`, `task:report`, `task:context:set` | conditional | conditional | Обещанный клиенту срок может отличаться от task.deadline. Нужны clientRef, promisedAt, связь обязательств с задачами и полнота выборки. |
| AM-03 | `task:report`, `task:brief` | conditional | conditional | Следующий шаг и следующее обновление требуют явных данных, не вывода из status или произвольного текста чата. |
| AM-04 | `task:change:propose`, `task:change:accept`, `task:update` | conditional | conditional | Нужен контракт исходных и новых требований, влияния на объём/сроки и решения согласующего. Запись решения отдельно от применения. |
| AM-05 | `task:result:list`, `task:delivery:record`, `task:feedback:record`, `task:acceptance:record`, `task:report` | conditional | conditional | Публичная передача и отзыв подтверждаются оператором/источником. Хранилище и схема событий ещё не приняты; команды не отправляют клиенту сообщения. |
| AM-06 | `task:chat:list`, `task:chat:send` | documented | conditional | Новая task-card переписка по маршрутам REST3 + IM; внешний клиент не получает права от роли аккаунт менеджера. |
| EMP-01 | `task:my`, `task:list` | documented | documented | Фильтрация по responsible/status/deadline выполняется локально после полного обхода доступных задач. Нужен заданный user ID; при cap результат явно partial. |
| EMP-02 | `task:show`, `task:chat:send`, `task:find`, `task:checklist:list`, `task:checklist:item:list` | documented | documented | Требования/материалы/критерии доступны в карточке и ссылках. Недостающие сведения запрашиваются через REST3 task chat. История обсуждения — дополнительный IM route. |
| EMP-03 | `task:blocker:raise`, `task:chat:send` | conditional | conditional | Чат позволяет сообщить проблему, но для поиска/закрытия препятствий нужны reason, needs, owner, next-step и источник этих фактов. |
| EMP-04 | `task:brief`, `task:change:propose`, `task:change:accept`, `task:show` | conditional | conditional | Чтобы отличить принятые ожидания от обсуждения, нужна явная модель согласования/истории. Нативная history пока только прежний маршрут. |
| EMP-05 | `task:result:add`, `task:result:from-message`, `task:file:attach`, `task:complete` | conditional | conditional | Предъявить result через v3 можно; переход на контроль, requireResult и post-condition complete нуждаются в lifecycle acceptance. |
| EMP-06 | `task:time:show`, `task:time:add`, `task:time:list`, `task:time:update`, `task:time:delete` | conditional | gap | V3 даёт чтение elapsedTime, но не документирует запись/исправление elapsed entries. Четыре legacy time маршрута явно допущены в MVP пользователем; strict-rest3 gap и runtime проверки сохраняются. |
| EMP-07 | `task:chat:list`, `task:chat:send` | documented | conditional | История IM и send v3 документированы для новой карточки. Действия выполняются от текущего подключения, не подставленного AUTHOR_ID. |

## Будущие acceptance проверки по JTBD

- **PM-01:** Создание с критериями и сроком; отсутствие обязательного creator; отказ в правах.
- **PM-02:** Недостающие blocker data => unknown; превышение scan cap => partial; входящие зависимости не заявлены полными.
- **PM-03:** Связь raise/resolve по ID; два противоречащих решения; отправка в нужный чат от авторизованного лица.
- **PM-04:** Предложение не изменяет fields; --apply применяет согласованные значения; частичный multi-step явно виден.
- **PM-05:** Контроль включён; проверяющий имеет approve rights; возврат содержит замечания; клиентская приёмка не подменяет внутреннюю.
- **PM-06:** Прочитать старые сообщения с LAST_ID; отправить и повторно прочитать MESSAGE_ID; отказ пользователю без доступа.
- **AM-01:** Карточка сохраняет source ref, client context и outcome; числовые creator/responsible обязательны.
- **AM-02:** Два разных срока не смешиваются; нет promisedAt => unknown; частичные данные не дают зелёный статус обязательства.
- **AM-03:** Сводка содержит evidence IDs/as-of/partial; неизвестный срок обновления не выдумывается; client audience не раскрывает внутреннюю переписку автоматически.
- **AM-04:** Предложение/согласование/применение различаются; конфликт текущих fields не затирается; виден согласующий и источник.
- **AM-05:** Передача имеет evidence и дату; отзыв имеет source ref; внутреннее approve не делает client accepted автоматически.
- **AM-06:** Уточнение и ответ читаются в task chat; верная принадлежность задачи/чата; отсутствует legacy comment route.
- **EMP-01:** Пагинация более страницы; свои задачи отделены от чужих; лимит выхода не прекращает сканирование до определения полноты.
- **EMP-02:** Получить related fields через select; вопросы связаны с задачей; загрузка локального файла не выдается за attach existing file.
- **EMP-03:** Нет нового status=blocked; просьба сохраняется в task chat; owner не назначается по догадке.
- **EMP-04:** Показать текущую принятую версию и различия; не объявлять любое чатное предложение согласованным.
- **EMP-05:** Нет result при requireResult => ошибка; needsControl => awaiting control; права и действительный статус проверены.
- **EMP-06:** Все четыре операции записей времени включены в MVP с явным REST 1.0 допуском. Time:show остаётся REST3. Для EMP-06 проверяются создание, полный список, исправление и удаление своих записей, права и принадлежность ENTRY_ID задаче. Strict-rest3 gap сохраняется; решение о scope не подтверждает runtime acceptance.
- **EMP-07:** Последовательное чтение FIRST_ID/LAST_ID; ответ находится при нужной задаче; отказ без участия/прав.

## Что закрыть до заявления о полном покрытии

1. Утвердить политику: новая task card с документированными IM companion routes либо строго любой запрос только REST3. При strict-rest3 полноценное чтение переписки пока не подтверждено.
2. Выбрать provider и схему рабочих фактов: client promise, blocker, change/approval, delivery, feedback и next update. Матрица называет необходимые факты и сохраняет unknown.
3. После MVP проверить семь v3 lifecycle mappings с needsControl/requireResult, правами и side effects перед их допуском. Их acceptance не является условием выпуска оставшихся команд MVP; legacy вызов не скрывается.
4. Для EMP-06 пользователь включил четыре команды записей времени в MVP с явным допуском прежнего API. Проверить список, создание, исправление и удаление своих записей; strict-rest3 gap сохраняется. Чтение elapsedTime не заменяет запись времени.
5. Проверить сканирование v3 list и реальные объёмы/доступность задач. Caps/ошибки/смена данных => partial; одна страница не является полной выборкой проекта.
6. Пройти перечисленные acceptance cases на тестовом портале при реализации с правами реальных участников; client acceptance и внутренний контроль проверяются отдельно.

Спецификация JTBD не ослабляется ради текущих API gaps. Каталог фиксирует варианты реализации и препятствия; актуальная граница MVP и согласованные API exceptions описаны ниже; runtime acceptance ещё не выполнен.


## MVP command mapping

После MVP — 28 команд; из оставшейся 31 операции 21 — кандидат MVP, 10 — API-policy-pending. Четыре time routes явно допущены; восемь checklist/item операций ещё требуют legacy API решения. Task:find включён в MVP. [Спецификация MVP](../../specs/task-mvp-scope/spec.md).

Структура ссылок не является оценкой полного/частичного выполнения JTBD. Все 19 продуктовых целей и API assessment сохраняются; optional discovery/checklist mappings не доказывают дополнительного acceptance.

| JTBD | Оставшиеся связи (кандидаты или API pending) | После MVP | Структура mapping |
| --- | --- | --- | --- |
| PM-01 | `task:add`, `task:subtask:add`, `task:show`, `task:checklist:add`, `task:checklist:item:add` | — | mapping-unchanged |
| PM-02 | `task:list` | `task:plan`, `task:risks`, `task:dependency:list` | mapping-reduced |
| PM-03 | `task:chat:send` | `task:blocker:raise`, `task:blocker:resolve` | mapping-reduced |
| PM-04 | `task:assign`, `task:deadline:set` | `task:plan`, `task:change:propose`, `task:change:accept` | mapping-reduced |
| PM-05 | — | `task:result:list`, `task:approve`, `task:disapprove`, `task:acceptance:record` | no-remaining-mapped-commands |
| PM-06 | `task:chat:list`, `task:chat:send` | — | mapping-unchanged |
| AM-01 | `task:add`, `task:show`, `task:find` | — | mapping-unchanged |
| AM-02 | — | `task:risks`, `task:report`, `task:context:set` | no-remaining-mapped-commands |
| AM-03 | — | `task:report`, `task:brief` | no-remaining-mapped-commands |
| AM-04 | `task:update` | `task:change:propose`, `task:change:accept` | mapping-reduced |
| AM-05 | — | `task:result:list`, `task:delivery:record`, `task:feedback:record`, `task:acceptance:record`, `task:report` | no-remaining-mapped-commands |
| AM-06 | `task:chat:list`, `task:chat:send` | — | mapping-unchanged |
| EMP-01 | `task:list` | `task:my` | mapping-reduced |
| EMP-02 | `task:show`, `task:chat:send`, `task:find`, `task:checklist:list`, `task:checklist:item:list` | — | mapping-unchanged |
| EMP-03 | `task:chat:send` | `task:blocker:raise` | mapping-reduced |
| EMP-04 | `task:show` | `task:brief`, `task:change:propose`, `task:change:accept` | mapping-reduced |
| EMP-05 | `task:file:attach` | `task:result:add`, `task:result:from-message`, `task:complete` | mapping-reduced |
| EMP-06 | `task:time:show`, `task:time:add`, `task:time:list`, `task:time:update`, `task:time:delete` | — | mapping-unchanged |
| EMP-07 | `task:chat:list`, `task:chat:send` | — | mapping-unchanged |

Структурные counts: mapping-unchanged 7, mapping-reduced 8, no-remaining-mapped-commands 4. Full-product API assessment: 7/12 и 4/14/1; это не score MVP.

- **PM-01:** Отражено только наличие оставшихся связей команд. Исходные условия/gaps и будущий acceptance сохраняются; состав MVP не доказывает полного выполнения JTBD. Дополнительные связи поиска и чек-листов описывают discovery/декомпозицию; checklist routes ещё API-policy-pending и не повышают доказанность покрытия.
- **PM-05:** Все связанные команды результатов и внутренней приёмки после MVP. PM-05 сохраняется как требование продукта; MVP не выполняет approve/disapprove или скрытый переход статуса.
- **AM-01:** Отражено только наличие оставшихся связей команд. Исходные условия/gaps и будущий acceptance сохраняются; состав MVP не доказывает полного выполнения JTBD. Дополнительные связи поиска и чек-листов описывают discovery/декомпозицию; checklist routes ещё API-policy-pending и не повышают доказанность покрытия.
- **AM-02:** Все связанные команды вынесены; клиентские обязательства относятся к развитию после MVP.
- **AM-03:** Все связанные сводки вынесены; отдельный workflow клиентского статусного отчёта после MVP.
- **AM-05:** Все исходные mapped команды, включая result:list, после MVP. Клиентская передача/отзыв/приёмка сохраняются общими требованиями продукта.
- **EMP-01:** task:my исключён; базовая выборка сотрудника остаётся через task:list --responsible ID. Это замена shortcut, а не утверждение runtime полноты всего JTBD.
- **EMP-02:** Отражено только наличие оставшихся связей команд. Исходные условия/gaps и будущий acceptance сохраняются; состав MVP не доказывает полного выполнения JTBD. Дополнительные связи поиска и чек-листов описывают discovery/декомпозицию; checklist routes ещё API-policy-pending и не повышают доказанность покрытия.
- **EMP-05:** В MVP остаётся только task:file:attach. Result:add/from-message и complete после MVP; прикрепление файла не выполняет предъявление native result или завершение задачи. Для будущего lifecycle сохраняются requireResult/needsControl, права и проверка side effects без скрытого создания результата.
- **EMP-06:** Все четыре операции записей времени включены в MVP с явным REST 1.0 допуском. Time:show остаётся REST3. Для EMP-06 проверяются создание, полный список, исправление и удаление своих записей, права и принадлежность ENTRY_ID задаче. Strict-rest3 gap сохраняется; решение о scope не подтверждает runtime acceptance.
