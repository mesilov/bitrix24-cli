# user-directory Specification

## Purpose

Предоставить предсказуемое чтение сотрудников и отношений оргструктуры через CLI, чтобы пользователь и автоматизация могли получить проверяемые ID для последующих рабочих действий.

## Requirements


### Requirement: Users namespace and current connection identity
CLI SHALL предоставлять `users:current`, `users:find`, `users:colleagues`, `users:manager`. `users:current` SHALL определять пользователя выбранного подключения через `user.current`, без вывода ID из URL webhook или профильных defaults. Справка и `list users` SHALL работать офлайн.

#### Scenario: Current webhook user
- **WHEN** выбран профиль work и выполнена `users:current --json`
- **THEN** `data.user` содержит пользователя фактического выбранного подключения
- **AND** `ID` является положительной десятичной строкой, остальные опубликованные поля сохраняют имена REST1, включая `NAME`, `LAST_NAME`, `SECOND_NAME`, `EMAIL`, `UF_DEPARTMENT`
- **AND** профильные `creatorId`, `responsibleId`, `observerId`, `managerId` не определяют текущего пользователя

#### Scenario: Available user fields
- **WHEN** scope позволяет получить ID и ФИО, но не возвращает EMAIL или UF_DEPARTMENT
- **THEN** current возвращает доступные поля без вымышленных значений и показывает отсутствующие дополнительные поля в meta.unavailableFields
- **AND** отсутствие либо невалидность обязательного ID считается ошибкой API, а не успешной карточкой

#### Scenario: Offline discovery
- **WHEN** без credentials вызывают `list users`, completion либо help новой команды
- **THEN** доступны четыре полных имени и их справка без API, чтения secrets или записи конфигурации

### Requirement: Employee search by name
`users:find --name TEXT` SHALL искать активных сотрудников компании по ФИО. Trimmed непустой запрос разбивается по пробелам на токены; каждый токен SHALL совпадать без регистра Unicode с буквальной подстрокой объединённых `NAME`, `SECOND_NAME`, `LAST_NAME`, независимо от порядка токенов. Должность, подразделение и regex SHALL не участвовать в этом поиске.

#### Scenario: Surname and first name in any order
- **WHEN** сотрудник имеет NAME Иван, SECOND_NAME Петрович, LAST_NAME Иванов и задан `--name "иванов Иван"`
- **THEN** сотрудник входит в результат, если доступная выборка достигла его в пределах scan budget
- **AND** совпадение только в должности или названии отдела не входит в результат

#### Scenario: Empty or conflicting search criteria
- **WHEN** не задан ни --name, ни --email, задан пробельный запрос или оба критерия одновременно
- **THEN** CLI возвращает usage-error/exit2 до credentials/API, с корректным примером

### Requirement: Employee search by email
`users:find --email EMAIL` SHALL искать активных сотрудников по точному `EMAIL` через серверный фильтр `user.get`. CLI SHALL trim запрос и требовать непустой корректный адрес без wildcard. Один и тот же email у нескольких сотрудников SHALL возвращать несколько записей.

#### Scenario: Exact email search
- **WHEN** задан `--email "ivan@example.test"`
- **THEN** запрос использует `FILTER.EMAIL` с точным адресом совместно с `ACTIVE=true`, `USER_TYPE=employee`
- **AND** совпадения по домену, подстроке адреса, должности или подразделению не добавляются

#### Scenario: Invalid email
- **WHEN** --email содержит пустую строку, wildcard или невалидный адрес
- **THEN** возвращается usage-error/exit2 до credentials/API

### Requirement: Search returns candidates without choosing an assignee
Поиск SHALL всегда возвращать список `data.items` для нуля, одного и нескольких результатов. CLI SHALL не превращать ФИО/email в единственный выбранный ID, не менять задачи и не сохранять результаты в config. Полный пустой результат SHALL завершаться exit0 с complete=true.

#### Scenario: Several matching employees
- **WHEN** ФИО или email совпадает у двух доступных сотрудников
- **THEN** CLI возвращает обе записи с разными ID и доступными сведениями для различения
- **AND** никакой сотрудник не назначается автоматически; единственное совпадение также остаётся списком

### Requirement: Explicit current department selection
`users:colleagues` и org-режим `users:manager` SHALL брать список отделов из `user.current.UF_DEPARTMENT`. При единственном отделе выбор автоматический; при нескольких SHALL требоваться `--department-id` одного из этих отделов. CLI SHALL не выбирать первый отдел или отдел из default.task.groupId.

#### Scenario: Several current departments
- **WHEN** UF_DEPARTMENT содержит 7 и 9, а --department-id не задан
- **THEN** возвращается ambiguous-department/exit2 с доступными ID и рекомендацией задать флаг
- **AND** выборка коллег или выбор руководителя не начинается

#### Scenario: Explicit department membership
- **WHEN** пользователь принадлежит отделам 7 и 9 и задаёт --department-id 9 либо посторонний ID 15
- **THEN** первый случай использует отдел 9; второй возвращает usage-error/exit2 после чтения текущего пользователя, до дальнейших запросов оргструктуры

#### Scenario: Missing organization data
- **WHEN** UF_DEPARTMENT отсутствует, содержит невалидные ID или является пустым списком
- **THEN** отсутствующие данные/членство возвращают organization-unavailable/exit1 с указанием нужного scope или настройки портала; невалидная форма возвращает api-error/exit1
- **AND** CLI не расширяет запрос до всей компании и не выдумывает текущий отдел

### Requirement: Colleagues are members of the current department
`users:colleagues` SHALL возвращать активных сотрудников выбранного текущего отдела через `user.get` с фильтрами `UF_DEPARTMENT`, `USER_TYPE=employee`, `ACTIVE=true`, исключая текущего пользователя. Руководитель отдела SHALL входить в список при совпадении членства; дочерние отделы и команды SHALL не добавляться.

#### Scenario: Department colleagues
- **WHEN** текущий сотрудник ID 1 и сотрудники ID 2, 3 принадлежат отделу 7, сотрудник 4 относится только к дочернему отделу 8
- **THEN** при выборе отдела 7 возвращаются 2 и 3 без 1 и 4
- **AND** meta содержит выбранный departmentId и currentUserId

#### Scenario: No visible colleagues
- **WHEN** полная разрешённая выборка отдела содержит только самого пользователя
- **THEN** результат — пустой data.items, exit0 и complete=true

### Requirement: Manager follows the selected organization branch
`users:manager` SHALL по умолчанию использовать `--source org`: найти ближайший `UF_HEAD`, отличный от текущего пользователя, в выбранном отделе либо по его цепочке `PARENT`. CLI SHALL никогда не возвращать самого пользователя как его руководителя. Цикл и превышение 100 отделов SHALL завершаться ошибкой с диагностикой.

#### Scenario: Immediate department manager
- **WHEN** у выбранного отдела UF_HEAD=8, пользователь подключения имеет ID 2 и user.get возвращает сотрудника 8
- **THEN** data.user содержит сотрудника 8, meta.source=org и найденный departmentId
- **AND** настроенный managerId=9 не подменяет результат

#### Scenario: User heads the department
- **WHEN** UF_HEAD совпадает с текущим ID либо отдел не имеет руководителя и PARENT ведёт к отделу с другим UF_HEAD
- **THEN** CLI проходит вверх до первого отличного руководителя и сообщает исходный и найденный отделы и пройденную цепочку

#### Scenario: Root has no different manager
- **WHEN** валидная цепочка достигла PARENT=0 и отличного руководителя нет
- **THEN** возвращаются data.user=null, complete=true, reason=no-manager и exit0 без вымышленного ID

#### Scenario: Broken hierarchy or unavailable manager
- **WHEN** отдел недоступен, необходимые данные цепочки отсутствуют, есть цикл, либо найденный UF_HEAD не возвращён user.get
- **THEN** возвращается organization-unavailable или api-error/exit1, без выбора другого профиля, постороннего руководителя или config fallback

### Requirement: Configured manager is an explicit source
`users:manager --source config` SHALL читать `default.users.managerId` только фактически выбранного сохранённого профиля и получать этого пользователя по ID. Он SHALL не обращаться к department.get, не записывать config и явно показывать source=config. Отсутствие настройки SHALL не запускать поиск по оргструктуре.

#### Scenario: Independent configured manager
- **WHEN** work задаёт managerId=9, а оргструктура назначает ID 8
- **THEN** --source config возвращает сотрудника 9, а --source org возвращает 8

#### Scenario: No configured manager for this source
- **WHEN** выбран env-file/process-env либо в профиле нет managerId
- **THEN** --source config возвращает configuration-error/exit1 с рекомендацией выбрать/настроить профиль без обращения к другому профилю или оргструктуре

#### Scenario: Incompatible manager options
- **WHEN** --source не равен org/config либо config-режим использует --department-id
- **THEN** возвращается usage-error/exit2 до credentials/API

### Requirement: Bounded lists and truthful completeness
`users:find` и `users:colleagues` SHALL поддерживать --limit=50, --all и --max-scan=1000. Limit ограничивает только вывод; all не отменяет scan budget. Пагинация SHALL следовать next с проверкой продвижения. Meta SHALL содержать scanned, matched, returned, limitApplied, complete и scope видимости подключения. Неполный просмотр SHALL возвращать partial-result/exit3.

#### Scenario: Match after the first page
- **WHEN** совпадение находится на следующей странице в пределах budget
- **THEN** CLI прочитывает страницу и включает его, без дубликатов ID

#### Scenario: Output limit and scan cap
- **WHEN** найдено 70 совпадений при limit=50 и просмотр завершён, либо max-scan исчерпан до завершения
- **THEN** первый случай возвращает 50 записей, matched=70, limitApplied=true и complete=true
- **AND** второй сохраняет найденное, сообщает причину, complete=false и exit3; --all меняет только число выводимых записей

#### Scenario: Later page failure or malformed pagination
- **WHEN** после полученных записей страница ошибочна, next повторяется или возвращены противоречащие ID/формы
- **THEN** результат частичный/exit3 с безопасной причиной; ошибка до получения данных возвращает api-error/exit1
- **AND** неизвестная полнота не выдаётся за доказанный полный список сотрудников компании

### Requirement: Stable user outputs and API policy
Users-команды SHALL сохранять общие JSON data/meta/error, TSV и human-output контракты. Published user fields SHALL сохранять REST1 имена; TSV SHALL иметь ID, NAME, SECOND_NAME, LAST_NAME, EMAIL, UF_DEPARTMENT. Только явно разрешённые REST1 маршруты SHALL выполняться; strict-rest3 SHALL блокировать команду до credentials/API. Timeout и Ctrl-C SHALL прекращать новые запросы.

#### Scenario: JSON and plain candidates
- **WHEN** поиск/коллеги запущены с --json либо --plain
- **THEN** JSON содержит один object и data.items array; TSV содержит указанные колонки без заголовка, с экранированием tabs/newlines и JSON-массивом UF_DEPARTMENT
- **AND** current/manager используют те же колонки для одной записи; отсутствующий manager даёт ноль TSV строк

#### Scenario: Strict REST3 policy
- **WHEN** любую из четырёх users-команд вызывают с --api-policy strict-rest3
- **THEN** возвращаются policy-denied/exit4 и ноль API calls, без чтения credential file, hidden fallback или изменения policy

#### Scenario: Read-only portal operations
- **WHEN** выполняется users-команда в стандартном режиме
- **THEN** в плане присутствуют только user.current, user.get, department.get в REST1 по потребности, meta отражает фактические API версии и безопасный источник подключения
- **AND** config и портал не изменяются, токен не попадает в stdout/stderr
