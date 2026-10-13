# Design

## Context

См. proposal.md. В origin/dev 0aeb25b TaskQuery содержит dueBefore, TaskInputMapper проверяет удобные/advanced фильтры, TaskListScanner выполняет bounded local scan через REST3 gateway; API route регистрируется в CommandCatalog и проверяется ApiPolicyGuard до выполнения. Skill живёт в отдельном репозитории и знает public CLI. MCP подключение проверено из worktree и реальными запросами search/details. Live-проверка показала отсутствие Filterable у deadline REST3 и работающий REST1 range.

## Goals / Non-Goals

**Goals:** общий temporal resolver для двух команд; реальная серверная передача дат; отдельный transport adapter REST1 с нормализацией; наблюдаемый API policy gate и полнота результата.

**Non-Goals:** изменение поля deadline в задачах, создание тестовых задач без отдельного разрешения, фактическая closed date, произвольный query language, перенос всех списков на REST1, новые профильные настройки timezone, реализация skill в этой постановке.

## Decisions

### 1. Readable canonical mnemonics

Рекомендованы --deadline-window today/tomorrow/yesterday/day-before-yesterday/this-week/this-month/this-quarter и независимый --overdue. Альтернатива day:0/day:-2/week:0/month:0/quarter:0 компактнее, но хуже читается; rolling next-7-days/past-30-days имеет другую семантику. Дополнительные aliases и расширения last/next/year вне MVP. Полный набор вариантов и user stories сохранён в issue #95.

### 2. Calendar resolver owns dates

Добавить типизированный temporal criterion в TaskQuery и общий resolver с инъецируемым Clock. Один now, timezone и интервал фиксируются до страниц. --timezone IANA выигрывает у timezone окружения, default явно сообщается; timezone портала не угадывается. Date boundaries вычисляются в календаре выбранного timezone и сериализуются с offset. --overdue обрезает верхнюю границу до now; пустое пересечение (например tomorrow + overdue) возвращает complete empty без сетевого запроса, сохраняя API policy gate.

### 3. Explicit REST1 gateway for temporal requests

При новом temporal criterion оба handler объявляют REST1 tasks.task.list до prepare; CommandCatalog и ApiPolicyGuard допускают эту комбинацию в обычной политике и запрещают в strict-rest3 с exit4. Новая ветка scanner использует REST1 adapter: filter object с >=DEADLINE/<DEADLINE, GROUP_ID для проекта, select uppercase, start pagination, result.tasks. Это прямой выбор маршрута, а не retry после REST3 ошибки. Существующий --due-before и остальные запросы не переводятся на новый маршрут.

REST1 ответы нормализуются в существующий public CLI data contract: числовые ID, deadline, status, groupId и прочие selected fields. Uppercase request keys сохраняются в API payload, нормализация ответа находится в adapter; точные enum/status mapping подтвердить по metadata и fixtures. title остаётся локальным Unicode literal predicate; не использовать TITLE wildcard. При --role использовать текущие role semantics и те же права владельца вебхука. Не вводить серверную оптимизацию других фильтров, пока её точный контракт не проверен.

### 4. Overdue policy

Выбран business rule deadline<now и незавершённость: pending/in_progress/supposedly_completed/deferred включаются; completed/declined исключаются. Это явно предложенное поведение, а не доказательство равенства мета-статусу Bitrix24 STATUS=-1. В REST1 REAL_STATUS и STATUS отличаются: первый является lifecycle enum, второй включает overdue subStatus=-1. Lifecycle predicate применяется локально после нормализации 2/3/4/5/6/7 в строковые статусы CLI; сервер фильтрует даты. Код не зависит от многозначного REAL_STATUS или мета-статуса STATUS=-1. Mapping подтверждён классической документацией, fake fixtures всех шести статусов и read-only CLI выборками текущего портала (не все lifecycle состояния есть в live fixtures). Документация и тесты должны закрепить одну политику; это не скрытый REST3 date fallback.

### 5. Preserve bounded scans and validation

Перенести общий сбор страниц/limit/max-scan/duplicates/partial на gateway-neutral contract, без изменения non-temporal поведения. REST1 next/total не принимать за project completeness сверх прав подключения. Исключить смешение temporal mode с --due-before/--where/--where-file; unknown/repeated token и неверный timezone — exit2 до сети. Явный --status пересекается с overdue без молчаливого переопределения; невозможное пересечение возвращает пустой результат. JSON temporal metadata содержит window, timezone, fromInclusive, toExclusive, now, route и место применения каждого фильтра без секретов.

## Risks / Trade-offs

- [REST1/REST3 различают enum и форму ответа] → изолированный adapter, проверенные fixtures и отдельные portal receipts.
- [MCP короткая выдача неполная и иногда возвращает другой article] → сохранять точный заголовок/URL результата, сверять полную страницу; не выдавать общий синтаксис filter за поддержку deadline.
- [DST/default timezone отличается у среды skill и CLI] → explicit IANA передача из skill, metadata, frozen-clock edge tests.
- [Мета-статус overdue может отличаться от бизнес-правила] → явно описать политику и проверить statuses отдельно; текущий receipt проверяет только диапазоны дат.
- [Совместное изменение scanner ломает bounded search] → regression checks для non-temporal REST3, budget/partial/order и новых REST1 pages.

## Migration Plan

Поставлять добавленные public flags после реализации и проверки в dev; обновить CHANGELOG/README и skill после доступности команды. Откат состоит в снятии temporal route/flags; других операций и remote task data это не затрагивает. Change не архивировать до завершения задач и verify.

## Implementation details confirmed

- now имеет точность секунды, одинаковую с API datetime payload; Clock инъецируется в resolver для frozen tests. Runtime default TZ затем date_default_timezone_get.
- Нормализованные REST1 статусы: 2 pending, 3 in_progress, 4 supposedly_completed, 5 completed, 6 deferred, 7 declined. Неизвестный статус/некорректный predicate даёт partial при проверке overdue; null deadline не совпадает.
- --status доступен также task:find для AND-композиции.
- Temporal --select ограничен документируемыми общими полями adapter; --order использует документированные classic сортировки. Неизвестные для маршрута поля отклоняются до API. README перечисляет оба набора; non-temporal allowlists не изменены.
- Данные live verification сохранены в live-cli-evidence.json. На этом портале не нашлось положительной проектной fixture; GROUP_ID проверен живой пустой выборкой и unit payload/AND проверками, без создания remote данных.
