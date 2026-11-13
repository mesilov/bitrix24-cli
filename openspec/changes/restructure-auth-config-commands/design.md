# Design

## Context

Зачем нужен change — см. proposal.md, раздел «Why». Требования — в delta specs `connection-configuration`, `connection-scope-validation` и `user-workflows`.

Текущее состояние `dev` на ff9481e:

- Шесть команд в `src/Console/Command/Connection/`: пять локальных (`effect: local`) и `connection:check` (`effect: read`, маршрут `[['scope', 1]]`). Общая логика — в `ConnectionCommandRunner`, хранение — в `ProfileRepository` (lock, атомарная запись, защищённые credential files), выбор подключения — в `ConnectionManager`, проверка прав — в `ScopeValidator` + `ScopeStateStore` + `ScopeCheckingTransport`.
- Снимок прав в `state.json` хранится по ключу SHA256 от credentials и источника. Замена вебхука автоматически даёт новый ключ и не наследует старый снимок.
- Строгие проверки значений (`integer literal`, strict boolean, `logging.level`, `fileMask`, `autoAddObserver → observerId`) уже реализованы при чтении `config.json` и покрыты общими fixtures схемы и runtime.
- Подсказки с `connection:*` зашиты в `Failure` у `ProfileRepository`, `ConnectionManager`, `ScopeValidator`, `ScopeCheckingTransport`, `SdkApiTransport`, а также в `TaskUsage`.

## Источники Битрикс24

Проверено 2026-11-12 через официальный MCP документации `https://mcp-dev.bitrix24.tech/mcp` (`b24-dev-mcp` 0.2.0, Streamable HTTP, без секретов): `bitrix-search`, затем `bitrix-method-details`.

- [`profile`](https://apidocs.bitrix24.ru/api-reference/common/users/profile.html): scope «базовый» (отдельный scope не нужен), возвращает `ID`, `ADMIN`, `NAME`, `LAST_NAME`, `PERSONAL_GENDER`, `TIME_ZONE`. У неактивного пользователя вместо объекта приходит `[]`. Ошибки: `INVALID_CREDENTIALS`, `NO_AUTH_FOUND`, `ACCESS_DENIED` (в том числе «REST is available only on commercial plans»), `QUERY_LIMIT_EXCEEDED` и др.
- [`scope`](https://apidocs.bitrix24.ru/api-reference/common/system/scope.html) уже используется `connection:check` без `full`. Правила — в `docs/connection-scopes.md`.

Повторная сверка перед реализацией, 2026-11-13: тот же endpoint по Streamable HTTP (JSON-RPC `initialize` → `tools/call`), `bitrix-search` («profile базовая информация о текущем пользователе») нашёл метод `profile`, `bitrix-method-details` для `profile` и `scope` подтвердил: scope «базовый» у обоих; `profile` возвращает объект с `ID` (строка), `ADMIN` (boolean), `NAME`, `LAST_NAME`, `PERSONAL_GENDER`, `TIME_ZONE`, для неактивного пользователя — `[]`; `scope` без `full` возвращает `string[]`. Секреты серверу не передавались.

Граница проверки: поведение `profile` на живом портале в этом change не проверялось. Задача приёмки в tasks.md проверяет `auth:login` и `auth:status` на тестовом портале, в том числе вебхук без scope `user`.

## Goals / Non-Goals

**Goals:**

- Разделить команды на `auth:*` (секрет, hostname, выбор) и `config:*` (несекретные настройки), не меняя форматы файлов.
- Сделать первый запуск короче: `auth:login work` → готово; при желании `auth:status`.
- Дать точечное изменение любой поддерживаемой настройки без повторного ввода вебхука.

**Non-Goals:**

- Совместимость с `connection:*`: алиасы, скрытые команды и предупреждения об устаревании не делаются.
- Изменение формата `config.json` (`version: 1`), `state.json`, credential files и JSON Schema.
- `config:edit` (открыть файл в редакторе) и произвольные ключи вне документированного списка.

## Decisions

1. **Пространства `auth:*` и `config:*`, а не одно `connection:*` с новыми подкомандами.** Повторяет `gh`/`glab` и разводит секретные операции и несекретные настройки. Рассматривался вариант оставить `connection:` и добавить `connection:set`. Он отклонён: смешение понятий, из-за которого возникла проблема, сохраняется, а имена не совпадают с тем, к чему привыкли пользователи и агенты.

2. **`auth:login` по умолчанию проверяет вебхук до записи.** Порядок такой:
   1. локальная проверка имени и входа: NAME, `-n`/`--webhook-stdin`, существующий профиль без `--replace`, конфликт stdin;
   2. ввод секрета;
   3. разбор URL;
   4. `profile`, затем `scope` с кандидатом;
   5. атомарная запись профиля, credentials и снимка прав.

   Если `profile` вернул ошибку авторизации или transport, или пустой `[]` для неактивного пользователя, ничего не сохраняется и команда завершается с exit 1 с безопасной диагностикой. Если `scope` не ответил, профиль сохраняется, снимок помечается unknown и выводится предупреждение. Нехватка прав относительно манифеста не считается ошибкой входа: профиль сохраняется, в stderr выводится отчёт, exit 0. Разным пользователям нужны разные подмножества команд, а точный ответ с exit 4 даёт `auth:status`. `--skip-check` пропускает шаг 4 для офлайна, CI-образов и `strict-rest3`. Альтернатива «сохранять всегда и проверять потом» отклонена: она повторяет проблему #152, когда ошибка видна поздно.

3. **Выбор по умолчанию: новый профиль становится активным, только если `defaultProfile` равен null.** Если профиль по умолчанию уже есть, он не меняется: добавление второго портала не должно переключать первый. Вывод `auth:login` явно сообщает, стал ли профиль профилем по умолчанию. Рассматривалось «всегда переключать», как `gh auth login`. Отклонено: у нас профили — разные порталы, а не аккаунты одного хоста, и молчаливое переключение рискованно для команд, меняющих данные.

4. **`--replace` меняет только секрет.** Требует существующий NAME. Новый credential file пишется рядом, ссылка в `config.json` переключается атомарно под lock, старый файл удаляется после публикации. `connection.hostname` обновляется по новому вебхуку, `name`, `default.*` и `defaultProfile` сохраняются. Снимок прав старого вебхука не наследуется (ключ state зависит от credentials). Без `--replace` существующий NAME даёт configuration-error до ввода секрета с подсказками `auth:login NAME --replace` и `config:set`.

5. **`auth:logout` удаляет профиль целиком** (метаданные и credentials) и очищает совпадающий `defaultProfile`. Вариант «оставлять настройки без секрета» отклонён: профиль без credentials нельзя использовать, а `config:list` выводил бы бесполезный профиль.

6. **`auth:list` остаётся локальным и отдельным от `auth:status`.** Список нужен агентам и скриптам мгновенно и без сети. В `gh` это одна сетевая команда `auth status`, но у нас проверка прав стоит запроса и меняет `state.json`. Блок «фактическое подключение» в `auth:list` заменяет инспекцию источника из `connection:show`: profile, `--env-file`, `B24CLI_ENV_FILE`, `BITRIX24_WEBHOOK`, безопасный hostname и путь, без чтения секрета.

7. **Ключи `config:*` — точные пути JSON и закрытый список.** Профильные ключи: `name`, `default.task.{groupId,creatorId,responsibleId,allowsTimeTracking}`, `default.users.{observerId,managerId,autoAddObserver}`. Корневые: `logging.{enabled,level,directory,fileMask,retentionDays}`, `telemetry.{collectProblemTrajectories,maxProblemTrajectoryFiles}`. Только для чтения в `config:get` и `config:list`: `telemetry.lastProblemTrajectoryProcessingAt`, `telemetry.lastProblemTrajectoryAnalysisOfferedAt`. Принадлежность к корню определяется префиксом, флага `--global` нет. Профильный ключ требует профиль, корневой с `--profile` даёт usage-error. Неизвестный ключ даёт usage-error со списком поддерживаемых. `defaultProfile` и `connection.*` через `config:*` недоступны: для них есть `auth:switch` и `auth:login --replace`.

8. **Разбор значений без угадывания типа.** VALUE — строка CLI, тип берётся из ключа:
   - ID — десятичный integer literal 1..PHP_INT_MAX без знака, пробелов, `1.0`/`1e0`;
   - boolean — только `true`/`false`;
   - `logging.level` — значение из списка в нижнем регистре;
   - `logging.directory` — непустая строка (null — через `unset`);
   - `fileMask` — правила `LoggingSettings`;
   - `name` — bare ASCII hostname.

   После подстановки весь документ проверяется тем же runtime-валидатором, что и при чтении. Межполевые правила (`autoAddObserver=true` требует `observerId`, `unset observerId` при `autoAddObserver=true`) отклоняются до записи. `config:unset name` запрещён (обязательное поле). Остальные `unset` удаляют ключ, и действует встроенное значение по умолчанию. Пустые контейнеры `default.task`, `default.users`, `logging`, `telemetry` после `unset` удаляются.

9. **Выбор профиля для `config:*` и `auth:status`.** Для `config:*` профиль берётся из `--profile`, затем `B24CLI_PROFILE`, затем `defaultProfile`. `BITRIX24_WEBHOOK` и `B24CLI_ENV_FILE` не являются профилями и игнорируются. `--env-file` вместе с `config:*` даёт usage-error. Нет профиля, а ключ профильный → usage-error с подсказкой `auth:login`/`auth:switch`. `auth:status` использует общий выбор подключения, как прежний `connection:check`, то есть работает и для env/file.

10. **Вывод.** Команды следуют общим контрактам human/plain/JSON:
    - `config:get` в plain печатает только значение, в JSON — `{key, value, source}`, где `source` — `profile`, `root` или `default`;
    - `config:list` в JSON содержит `profile`, `settings[]` с `key/value/source` и блок `logging` со статусом `ok|disabled|invalid`, путями каталога и файла текущих суток;
    - `config:set`/`config:unset` возвращают новое эффективное значение;
    - `auth:status` в JSON сохраняет форму отчёта `connection:check` (успех — `data`, нехватка прав — `error.details.scopeValidation`) и добавляет `owner: {ID, NAME, LAST_NAME, ADMIN}`. Поля владельца сохраняют имена и значения REST 1.0 `profile`: правило репозитория запрещает собственные синонимы API-полей, а `users:current` уже выводит `ID`/`NAME`/`LAST_NAME` в том же виде.
    - `auth:login` возвращает `data: {profile, name, hostname, isDefault, becameDefault, replaced, previousHostname, verified, owner}` и отчёт прав в `meta.scopeValidation`; `auth:logout` — `wasDefault` и `portalWebhookRevoked: false`.
    - Пояснения локальных команд (профиль по умолчанию, замена hostname, недостающие права, фактическое подключение, статус логирования) передаются в `meta.notices[]`; в human/plain те же строки печатаются в stderr.

11. **Маршруты и effects в `CommandCatalog`.**
    - `auth:status`: `read`, маршрут `[['profile', 1], ['scope', 1]]`;
    - `auth:login`: свой effect `credentials` с сетевой проверкой; при `--skip-check` поведение как у `local`;
    - `auth:list`, `auth:switch`, `auth:logout`, `config:*`: `local`.

    `ScopeManifest::OTHER_METHODS` получает `'profile' => []`. Ревизия манифеста меняется, и снимки один раз обновляются при следующем реальном вызове. Это ожидаемо.

12. **Документация.** `docs/connection.md` → `docs/auth.md`, `docs/connection-scopes.md` → `docs/auth-scopes.md`, все внутренние ссылки обновляются. `docs/config.md` получает раздел «Команды config» вместо «Настройки меняют вручную». README описывает первый запуск как `auth:login work` + `auth:status`, без упоминания `connection:*`.

## Уточнения при реализации

1. **`auth:login` зарегистрирован в `CommandCatalog::LOCAL_COMMANDS` с effect `credentials`**, а его маршруты `profile`/`scope` — в `ROUTES`. Команду исполняет `AuthCommandRunner`, политику проверяет общий `ApiPolicyGuard` по этим маршрутам. `auth:status` остаётся в `COMMANDS` (effect `read`) и имеет интеграционный тест `tests/Integration/Auth/StatusCommandTest.php`.
2. **Проверка идёт от имени будущего профиля.** `ConnectionManager::candidate()` выбирает ещё не сохранённый вебхук как профиль NAME, поэтому ключ снимка в `state.json` совпадает с ключом сохранённого профиля. `ScopeValidator` разделён на `probe()` (сеть, без записи) и `remember()` (запись): права читаются до сохранения профиля, снимок пишется после публикации.
3. **Ошибка проверки сохраняет исходный код ошибки** (`permission-denied`, `transport-error`, `api-error`, `connection-unavailable`) и дополняет сообщение: профиль не сохранён, подсказка `--skip-check`. Неактивный пользователь вебхука (`profile` вернул `[]`) — `connection-unavailable` / exit 1 и в `auth:login`, и в `auth:status`.
4. **Hostname фактического подключения в `auth:list`.** Для сохранённого профиля hostname берётся из метаданных, credential file не открывается. Для `BITRIX24_WEBHOOK`, `--env-file` и `B24CLI_ENV_FILE` hostname вычисляется из значения источника, наружу выходит только он. Неразрешимый источник (отсутствующий env-файл, конфликт переменных) не скрывает список профилей: блок получает `source: unresolved` и причину.
5. **`auth:login`, `auth:switch` и `auth:logout` адресуют профиль аргументом NAME** и отклоняют `--profile`/`--env-file` как usage-error, чтобы глобальный selector не расходился с NAME.
6. **Исправление неверного `logging`.** `config:set logging.*`/`config:unset logging.*` принимается, если после изменения блок целиком корректен. Поэтому неверное поле исправляется теми же командами, а изменение другого поля при неверном соседнем отклоняется с причиной.
7. **Отсутствующий профиль сообщается до блокировки хранилища**: `auth:switch`, `auth:logout` и `config:set` для несуществующего профиля не создают каталог и lock file.
8. **`ProfileRepository::add()` удалён.** Профиль создаёт только `login()`; значения по умолчанию задаются `set()`. Тесты заполняют профили через `tests/Support/ProfileFixtures.php` поверх публичных `login()` и `transaction()`.
9. **Native smoke** (`build/tests/connection-smoke.py`) входит офлайн через `--skip-check`, а проверяемый вход выполняет через управляемый SDK transport `runtime-probe connection-fixture`, который дополнен последовательностью `profile` → `scope`.

## Risks / Trade-offs

- [Агенты и скрипты, вызывающие `connection:*`, сломаются после обновления] → release notes и CHANGELOG с таблицей соответствия; синхронное обновление `bitrix24-cli-skill`; `b24cli list auth` и `list config` в справке. Сознательно без алиасов (решение пользователя для 0.*).
- [`auth:login` стал сетевым и может зависнуть или упасть в офлайне] → ограничения запроса как у прочих команд, понятная ошибка с подсказкой `--skip-check`, `--skip-check` в примерах для CI.
- [Неудача после `profile` при записи оставит рассогласование] → запись профиля, credentials и снимка одной транзакцией под lock, как сейчас в `add`; снимок пишется после публикации профиля, его отсутствие безопасно (unknown).
- [Гонка `config:set` с `auth:*` или `doctor`] → общий lock конфигурации и повторное чтение под lock перед изменением; существующие тесты конкурентной записи расширяются на `config:set`.
- [`--replace` при смене hostname меняет портал под тем же NAME] → вывод показывает старый и новый hostname; `default.*` с ID другого портала остаются. Это документируется, автоматической очистки нет.
- [Ревизия манифеста меняется из-за `profile`] → один дополнительный запрос `scope` у существующих пользователей после обновления, в пределах суточной логики.

## Migration Plan

Миграции данных нет: файлы остаются в прежнем формате и читаются новыми командами без изменений. Пользователь после обновления заменяет вызовы по таблице в CHANGELOG. Откат — предыдущий бинарник: формат файлов не менялся.
