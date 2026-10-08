# Проверка потенциального CLI на полноту JTBD

Дата: 2026-10-09. Основания: [основная spec](../../specs/task-workflows/spec.md), [каталог](cli-candidates.md), [JSON проверки](jtbd-coverage.json). Проверка статическая, на уровне проектирования; команд в приложении нет.

## Итог

Все **19/19 JTBD имеют кандидатные команды**, но это не 19/19 доказанных сценариев:

- Для новой карточки: **7 сценариев имеют документированные основные маршруты**, **12 условны** из-за рабочих данных, lifecycle semantics, полноты или исключений API.
- Для строго только REST3: **4 имеют документированные основные маршруты**, **14 условны**, **EMP-06 имеет gap записи/исправления времени**.
- Три ролевых chat сценария входят в 7 документированных маршрутов новой карточки, но требуют IM для истории и поэтому условны в strict-rest3.
- Native v3 покрывает card/result/send, а не автоматически согласования, обещания клиенту и устранение препятствий.
- Реализовано/проверено на портале: **0** этих JTBD. Ни one-to-one mapping имени команды, ни наличие SDK wrapper не являются runtime evidence.

Статус documented означает наличие официально описанного основного маршрута с указанными предпосылками. Он не означает успешный acceptance, отсутствие правовых/ресурсных ограничений или готовый алгоритм. Conditional означает явно оставшееся существенное решение; gap — нет документированного требуемого v3 write route.

## Матрица 19 сценариев

| JTBD | Кандидатные команды | Новая карточка | Strict REST3 | Условие / gap |
| --- | --- | --- | --- | --- |
| PM-01 | `tasks:create`, `tasks:subtask:create`, `tasks:get` | documented | documented | Для каждого задания заданы title, creator/responsible, deadline и критерии в description. Зависимости/чек-листы — отдельные расширения, не обязательны для базовой декомпозиции. |
| PM-02 | `tasks:list`, `tasks:plan`, `tasks:risks`, `tasks:dependency:list` | conditional | conditional | Просрочки вычисляются; полная выборка локально фильтруется. Для blockers/ожиданий нужен утверждённый источник; v3 gantt.list даёт только исходящие связи. |
| PM-03 | `tasks:blocker:raise`, `tasks:blocker:resolve`, `tasks:chat:send` | conditional | conditional | Нужен контракт событий препятствия, owner/next-step и конфликтных изменений. Чатное уведомление не гарантирует прочтение. |
| PM-04 | `tasks:plan`, `tasks:assign`, `tasks:deadline:set`, `tasks:change:propose`, `tasks:change:accept` | conditional | conditional | Само update v3 документировано; согласование и ресурсные ограничения требуют данных/протокола. Ответственный не равен процедуре delegate. |
| PM-05 | `tasks:result:list`, `tasks:approve`, `tasks:disapprove`, `tasks:acceptance:record` | conditional | conditional | REST3 status writable в модели, но эквивалентность approve/disapprove требует acceptance. До него специальные команды disabled. |
| PM-06 | `tasks:chat:list`, `tasks:chat:send` | documented | conditional | Отправка REST3, история через документированный IM companion route; только участник task chat с правами. |
| AM-01 | `tasks:create`, `tasks:get` | documented | documented | Для базового сценария источник запроса, клиентский контекст и ожидание вводятся оператором в description. Автоматический импорт из CRM не требуется и не обещается. |
| AM-02 | `tasks:risks`, `tasks:report`, `tasks:context:set` | conditional | conditional | Обещанный клиенту срок может отличаться от task.deadline. Нужны clientRef, promisedAt, связь обязательств с задачами и полнота выборки. |
| AM-03 | `tasks:report`, `tasks:brief` | conditional | conditional | Следующий шаг и следующее обновление требуют явных данных, не вывода из status или произвольного текста чата. |
| AM-04 | `tasks:change:propose`, `tasks:change:accept`, `tasks:update` | conditional | conditional | Нужен контракт исходных и новых требований, влияния на объём/сроки и решения согласующего. Запись решения отдельно от применения. |
| AM-05 | `tasks:result:list`, `tasks:delivery:record`, `tasks:feedback:record`, `tasks:acceptance:record`, `tasks:report` | conditional | conditional | Публичная передача и отзыв подтверждаются оператором/источником. Хранилище и схема событий ещё не приняты; команды не отправляют клиенту сообщения. |
| AM-06 | `tasks:chat:list`, `tasks:chat:send` | documented | conditional | Новая task-card переписка по маршрутам REST3 + IM; внешний клиент не получает права от роли аккаунт менеджера. |
| EMP-01 | `tasks:my`, `tasks:list` | documented | documented | Фильтрация по responsible/status/deadline выполняется локально после полного обхода доступных задач. Нужен заданный user ID; при cap результат явно partial. |
| EMP-02 | `tasks:get`, `tasks:chat:send` | documented | documented | Требования/материалы/критерии доступны в карточке и ссылках. Недостающие сведения запрашиваются через REST3 task chat. История обсуждения — дополнительный IM route. |
| EMP-03 | `tasks:blocker:raise`, `tasks:chat:send` | conditional | conditional | Чат позволяет сообщить проблему, но для поиска/закрытия препятствий нужны reason, needs, owner, next-step и источник этих фактов. |
| EMP-04 | `tasks:brief`, `tasks:change:propose`, `tasks:change:accept`, `tasks:get` | conditional | conditional | Чтобы отличить принятые ожидания от обсуждения, нужна явная модель согласования/истории. Нативная history пока только прежний маршрут. |
| EMP-05 | `tasks:result:add`, `tasks:result:from-message`, `tasks:file:attach`, `tasks:complete` | conditional | conditional | Предъявить result через v3 можно; переход на контроль, requireResult и post-condition complete нуждаются в lifecycle acceptance. |
| EMP-06 | `tasks:time:show`, `tasks:time:add`, `tasks:time:list`, `tasks:time:update` | conditional | gap | V3 даёт чтение elapsedTime, но не документирует запись/исправление elapsed entries. Нужны явные legacy time exceptions либо оставить сценарий вне strictly-v3 MVP. |
| EMP-07 | `tasks:chat:list`, `tasks:chat:send` | documented | conditional | История IM и send v3 документированы для новой карточки. Действия выполняются от текущего подключения, не подставленного AUTHOR_ID. |

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
- **EMP-06:** Собственная запись seconds/comment; исправление author/admin; не секундомер; проверка полного списка entries отдельно.
- **EMP-07:** Последовательное чтение FIRST_ID/LAST_ID; ответ находится при нужной задаче; отказ без участия/прав.

## Что закрыть до заявления о полном покрытии

1. Утвердить политику: новая task card с документированными IM companion routes либо строго любой запрос только REST3. При strict-rest3 полноценное чтение переписки пока не подтверждено.
2. Выбрать provider и схему рабочих фактов: client promise, blocker, change/approval, delivery, feedback и next update. Матрица называет необходимые факты и сохраняет unknown.
3. Проверить семь v3 lifecycle mappings с needsControl/requireResult, правами и side effects. Until then команды disabled; legacy вызов не скрывается.
4. Для EMP-06 разрешить отдельные прежние time methods либо признать сценарий вне strictly-v3 первого этапа. Чтение elapsedTime не закрывает запись и исправление.
5. Проверить сканирование v3 list и реальные объёмы/доступность задач. Caps/ошибки/смена данных => partial; одна страница не является полной выборкой проекта.
6. Пройти перечисленные acceptance cases на тестовом портале при реализации с правами реальных участников; client acceptance и внутренний контроль проверяются отдельно.

Спецификация JTBD не ослабляется ради текущих API gaps. Каталог фиксирует варианты реализации и препятствия; окончательный MVP и исключения пользователь ещё не принял.
