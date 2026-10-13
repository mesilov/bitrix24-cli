# Design

## Context

Основание — proposal.md и issue #63, milestone 0.2.0. Worktree создан от актуальной origin/dev `45895884c221fb363eb284a4d77d0641eddbf58e`; основной dirty checkout сохранён.

Наблюдаемая реализация: CommandCatalog регистрирует 30 task-команд и 5 локальных connection-команд. CommandRunner/ApiPolicyGuard/ResultPresenter уже реализуют выбор подключения, API policy, error boundary и data/meta/error. ProfileRepository принимает только default.task; JSON Schema и runtime проверяются общими fixtures. Application/Task/Port/UserGateway и Rest1UserAdapter обслуживают обогащение участников задач по ID, без current/search/оргструктуры. IntegrationCommandCoverageTest и LiveTaskCommandTestCase привязаны к Task namespace и task fixtures.

Уточнение пользователя: наблюдателя добавлять, если включён флаг автодобавки; при отсутствии метода в REST3 использовать REST1. Стандартный режим уже допускает выбранные REST1 маршруты. strict-rest3 — существующая явно выбираемая политика; её regression coverage не является новым ограничением обычных users-сценариев.

## Goals / Non-Goals

**Goals:** отдельная граница чтения пользователей и оргструктуры; предсказуемые кандидаты вместо name-to-ID assignment; профильные роли с opt-in наблюдателем; сохранение существующего pipeline вывода, policy и учёта частичного выполнения.

**Non-Goals:** user/department CRUD, автоматическая синхронизация config с оргструктурой, назначение ответственного по текстовому имени, HR teams, изменение семантики существующих task flags, новый policy режим или выпуск релиза. Состав JTBD задаётся user-workflows; подробные observable contracts — в delta specs.

## Decisions

### 1. Four commands under users

| Command | Input | Route / source |
| --- | --- | --- |
| users:current | Global connection selector | user.current v1 |
| users:find | Exactly one --name or --email; --limit/--all/--max-scan | user.get v1 |
| users:colleagues | Optional --department-id; --limit/--all/--max-scan | user.current + user.get v1 |
| users:manager | --source org (default) or config; --department-id only for org | org: user.current + department.get + user.get v1; config: profile metadata + user.get v1 |

Namespace users следует прямому запросу пользователя. Вводить дополнительные user:* или whoami aliases не требуется. Применяются общие connection selectors, --json/--plain, timeout и cancellation. Users reads не требуют новых write/dry-run flags.

### 2. Exact employee search with an explicit scan budget

Для ФИО используем user.get с FILTER.USER_TYPE=employee и FILTER.ACTIVE=true, sort=ID, order=ASC и start, а token matching выполняем локально по NAME/SECOND_NAME/LAST_NAME через Unicode case-insensitive literal substring. Все токены должны присутствовать; порядок ФИО в запросе не важен. Полный поиск следует пагинации до исчерпания либо max-scan=1000; output limit=50 не останавливает просмотр. all снимает только output limit. Параметры limits принимают positive integer; output limit не смешивается с scan budget.

Альтернатива user.search/FIND быстрее для больших каталогов, но ищет также должность и название отдела, а его поисковые semantics не гарантируют выбранный локальный контракт ФИО. Поэтому не объявляем полноту ФИО на основании broad server search. В будущем отдельная оптимизация может сохранить те же observable contracts. Для email используем точный FILTER.EMAIL совместно с employee/ACTIVE; wildcard и поиск только по домену не вводятся. CLI не обещает undocumented case-folding email сервером.

Публикуемый subset: ID, NAME, SECOND_NAME, LAST_NAME, EMAIL, UF_DEPARTMENT. ID — canonical positive decimal string; UF_DEPARTMENT — проверенный список positive integer IDs, если доступен. API fields сохраняют имена; display-колонки не переименовывают payload. Отсутствующее optional email/department metadata обозначается как unavailable; отсутствие обязательных name данных для локального matching означает partial, а не отсутствие совпадения. Пагинация проверяет next, формы списков, advancing cursors, duplicate/conflicting IDs, budget, timeout и cancellation.

### 3. Organization relationships are based on actual membership

current.UF_DEPARTMENT определяет текущий отдел. Единственный ID выбирается автоматически; при нескольких требуется --department-id из этой выборки. Colleagues фильтрует ровно выбранный отдел, active employees и исключает acting user. Group/project IDs и дочерние отделы не используются.

Manager org обходит выбранный отдел и цепочку PARENT. Первый валидный UF_HEAD, отличный от acting user, является результатом алгоритма CLI; если acting user руководит отделом или head отсутствует, проверяется родитель. Visited set и предел 100 отделов запрещают цикл/неограниченный обход. Валидный PARENT=0 без подходящего head даёт user=null, reason=no-manager. Недоступный отдел, missing required hierarchy field или отсутствующая карточка найденного head дают честную ошибку. Это предлагаемая semantics CLI, а не утверждение о специальном manager endpoint API.

Альтернатива «первый UF_DEPARTMENT / первый head» не представляет однозначного руководителя. im.department.colleagues.list и HR node member APIs имеют другую границу поиска и не вводятся как fallback в этот change.

### 4. Profile role selectors and opt-in flag

Пример части выбранного профиля:

```json
{
  "default": {
    "task": {
      "creatorId": 2,
      "responsibleId": 2,
      "allowsTimeTracking": false
    },
    "users": {
      "observerId": 5,
      "managerId": 9,
      "autoAddObserver": true
    }
  }
}
```

observerId/managerId — служебные role selectors CLI; у Bitrix24 нет одноимённых полей в используемых user/department/task payload. Они не являются альтернативными именами API fields и не попадают в REST3 fields. default.task сохраняет только свой существующий контракт writable REST3 fields. В user records используются API ID/UF_DEPARTMENT, в department records — ID/PARENT/UF_HEAD, при назначении наблюдателя — AUDITORS REST1.

default.users object и его поля optional; отсутствующий autoAddObserver означает false. ID — positive integer literal до PHP_INT_MAX; boolean строгий. true требует observerId на уровнях JSON Schema и runtime. Никакие role defaults не вычисляются из credentials или org. Manager config используется только с --source config; отказ не переключает источник.

connection:add получает --observer-id, --manager-id и --auto-add-observer true|false, сохраняет настройки атомарно в существующем storage flow; list/show показывают их без secrets. Для существующего профиля сохраняется документированный способ редактирования config.json, без добавления отдельного connection:update в этот change. JSON Schema, load/validate/publish, writer и docs должны поддерживать empty object users, optional containers и shared corpus; CLI различает object и array до associative decode. Эффективные роли доступны через ConnectionManager только для фактически выбранного saved profile. Не менять signature defaults(), который обслуживает default.task; добавить отдельный accessor рабочих ролей.

### 5. Auto-observer is a companion workflow after creation

При autoAddObserver=false/omission task:add сохраняет нынешний REST3 plan и output. При true подготовленный request получает observer ID отдельно от fields. План включает tasks.task.add v3, чтение participants через tasks.task.get v1 и возможный tasks.task.update v1. Advanced field metadata preflight сохраняется отдельно.

После подтверждённого create ID читаем текущие AUDITORS и назначаем union(existing, configured), не меняя ACCOMPLICES или прочие поля. Если observer уже есть, update не нужен. Не подменять невалидный список пустым: такой ответ вызывает partial после создания. Это сохраняет наблюдателей, которых портал назначил по собственным правилам.

Существующий ParticipantGateway/Rest1ParticipantAdapter предоставляет ids()/set(); используем этот контракт и method-specific подтверждение update task ID. Ответ ACK доказывает выполнение шага, а live acceptance дополнительно проверяет membership через SDK readback. До create policy проверяет все потенциальные маршруты. Таким образом обычный режим использует v1 там, где v3 не предоставляет capability; явно выбранная strict policy отклоняет non-v3 план целиком, до побочного эффекта.

Creation и companion assignment имеют отдельные ledger entries. Успешный data.operation и известный taskId сохраняются, ledger/complete добавляются в meta. При read/update failure после create возвращается data.operation.taskId, complete=false, exit3 и failed/unknown шаг; cancellation сохраняет 130. ExecutionLedger можно переиспользовать для учёта, адаптировав result к существующему task:add data.operation, а не меняя успешный output на несвязанный список. Create с неизвестным результатом не запускает companion и не повторяется. Нет automatic rollback/delete или retry write.

Basic --dry-run показывает create и companion routes с reference будущего ID и не читает credentials/API. Advanced --fields dry-run сохраняет существующий remote schema preflight; нельзя объявлять его полностью offline.

При совмещении с `--tag` сохраняется порядок create → TAGS → чтение AUDITORS → union update. Ошибка записи тегов останавливает observer companion; ledger сохраняет созданную задачу и помечает observer-шаги skipped. Ошибка observer-шагов сохраняет подтверждённую запись тегов. Basic dry-run показывает все четыре шага до credentials/API.

### 6. User module and shared runtime

Новые Console/Command/Users команды используют общий CommandRunner, отдельные typed requests/handlers в Application/User и ports UserDirectoryGateway/DepartmentGateway. Infrastructure adapters используют существующий ApiTransport и method/version allow-list; HTTP/SDK не вызываются напрямую из command/handler. Task UserGateway не расширяется current/search/org обязанностями; можно разделить низкоуровневый record validation при доказанной необходимости, не меняя contract task enrichment.

CommandCatalog, config/services.php и presenter получают explicit регистрации/профили user-card/user-list. Новые remote маршруты допускаются по command/method/version, без blanket allow всех user/department methods. Существующие global errors, secrets redaction, quiet/plain/JSON и RuntimeState сохраняются. Internal method names не становятся публичным raw-call escape hatch.

### 7. Integration ownership for two namespaces

Обобщить common live command harness для production bootstrap, selected connection, одного command:<name> owner и записи bounded evidence. LiveTaskCommandTestCase остаётся task fixture specialization, LiveUsersCommandTestCase использует только read SDK fixtures и не создаёт/редактирует сотрудников или оргструктуру. IntegrationCommandCoverageTest определяет module namespace из command class и требует отдельный class/happy-path для каждого users command, сохраняя task coverage и guard против другого CLI command.

Users happy paths: current сравнивает ID с SDK current; find берёт проверяемое ФИО/email видимого employee через SDK; colleagues сравнивает membership выбранного доступного отдела; manager сверяет org chain либо настроенный manager fixture без изменения оргструктуры. Нет live data/scope — bounded skip с причиной, не pass. Task:add auto-observer happy path использует собственную disposable task и проверяет сохранённый configured observer/прочих observers через independent SDK readback; cleanup только своих fixtures.

## Official API evidence

Проверено 2026-10-13 через официальный Bitrix24 documentation MCP; при неполном/смешанном описании MCP открыта конкретная официальная страница нужной версии.

| Source | Verified contract used |
| --- | --- |
| [user.current](https://apidocs.bitrix24.ru/api-reference/user/user-current.html) | Пользователь фактического токена; ID/имена/UF_DEPARTMENT зависят от доступных полей user scope. |
| [user.get](https://apidocs.bitrix24.ru/api-reference/user/user-get.html) | FILTER, employee/ACTIVE, UF_DEPARTMENT и EMAIL; select, sort/order, start, next, страницы по 50. Параметры/поля сохраняют документированный регистр. |
| [user.search](https://apidocs.bitrix24.ru/api-reference/user/user-search.html) | Рассмотренная альтернатива FIND охватывает ФИО, должность и название отдела; не используется в выбранном контракте строгого поиска по ФИО. |
| [department.get](https://apidocs.bitrix24.ru/api-reference/departments/department-get.html) | department scope; запрос ID, данные PARENT/UF_HEAD; org IDs относятся к структуре компании. |
| [task fields REST3](https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html) | auditors — read relation; write via REST3 add/update не поддержан, используется classic update с AUDITORS. |
| [classic tasks.task.update](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-update.html) | REST1 companion с taskId/fields; ответ содержит task.id. Не смешивать с одноимённым REST3 update. |

Документация подтверждает контракт выбора API, а не доступность конкретного webhook/портала. В этом planning этапе runtime, schema, PHP tests, live membership и package acceptance ещё не проверялись. Новые validation/pagination/manager semantics выше — решения проекта, подлежащие implementation tests.

## Risks / Trade-offs

- [Имя требует просмотра employee directory] → max-scan, truthful partial, narrow email route; дальнейшая оптимизация сохраняет контракт ФИО.
- [Scope скрывает поля/оргструктуру] → unavailableFields для optional данных и явные errors/partial для требуемых, без расширения прав или fallback другого портала.
- [Несколько отделов или пользователь сам head] → explicit department, bounded parent traversal и no-manager результат.
- [Create успешен, companion не выполнен] → taskId и ledger сохранены, partial/unknown вместо повторного create.
- [На портале уже назначены observers] → read-and-union вместо замены одним default ID. Atomic append API не заявляется; race после чтения остаётся ограничением read/update.
- [Integration harness исторически Task-specific] → общий command ownership и отдельные fixtures без ослабления покрытия старых команд.

## Migration Plan

Весь change доставляется feature PR в dev после apply/verify; planning PR остаётся draft до реализации. Existing valid configs без default.users принимаются без изменения bytes; новые роли имеют default auto-add false. README до 1.0 описывает текущий согласованный интерфейс без migration narration.

При откате реализации пользователь явно убирает новые default.users fields до запуска старого строгого parser; автоматическое удаление config или изменение credentials не производится. После завершённой реализации и проверки change синхронизируется/архивируется по maintainer workflow; planning не архивируется как completed и issue не закрывается до delivery.
