# Инвентаризация справки #113

База: `dev` после PR #115, `1741b9c`; Symfony Console 8.1.8. Offline-инвентаризация ApplicationFactory: 47 публичных команд. Скрытая `_complete` не входит в пользовательский каталог.

## Переводимые области

| Область | Строки / источник | Проверка |
| --- | --- | --- |
| Root help | Usage placeholders, глобальные описания, группировка — ApplicationInformation/B24Application | --help и -h; динамический реестр и один блок «Общие» |
| Все команды | Назначение, аргументы/опции, processed help, примеры — src/Console/Command | Обе формы help для каждой строки каталога ниже |
| Symfony builtins | completion/help/list: назначения, аргументы/опции, installation/format/raw пояснения | Русская metadata и сохранение definitions/completion suggestions |
| Текстовый descriptor | Usage/Description/Arguments/Options/Help, default/multiple values annotations | Русские заголовки, значения default и повторяемые опции |
| Markdown help | Usage/Arguments/Options, свойства опций/аргументов | Русская разметка; literal defaults без изменений |
| XML/JSON help | Человеческие description/help | Русская metadata; схемы и ключи исходных formatters |
| Документация | README, cli-reference generator, CLI-карта | make cli-reference-check, валидные примеры |

Смешанные пояснения: envelope/columns, companion route, advanced/writable/editable, temporal flags, timezone/default, metadata/credentials, redirected stdin, snapshot/preview, regular file/upload/attach, partial/subtree/scope. Переводится смысл, а не буквальные фрагменты примеров.

## Сохраняемые идентификаторы

Не переводятся имена команд/флагов/аргументов; API/JSON-поля (включая default.task, meta.*, schemaVersion, cardIds); enum/status/action и shell имена; REST/JSON/TSV/ANSI/Unicode/IANA/ISO8601; пути/URL; автор/лицензия. Режимы опций/аргументов, shortcuts, defaults и completion suggestions сравниваются с базой. Генерируемые shell-скрипты остаются кодом исходной библиотеки. API-контракты и операции не изменяются.

## Полный публичный каталог

Числа опций включают общие опции приложения после mergeApplicationDefinition(false).

| Команда | Аргументов | Опций |
| --- | --- | --- |
| `completion` | 1 | 17 |
| `connection:add` | 1 | 25 |
| `connection:list` | 0 | 16 |
| `connection:remove` | 1 | 16 |
| `connection:show` | 1 | 16 |
| `connection:use` | 1 | 16 |
| `doctor:problem-trajectory` | 0 | 26 |
| `help` | 1 | 18 |
| `list` | 1 | 19 |
| `task:access:show` | 1 | 16 |
| `task:add` | 0 | 27 |
| `task:assign` | 1 | 18 |
| `task:chat:delete` | 1 | 19 |
| `task:chat:list` | 1 | 21 |
| `task:chat:send` | 1 | 19 |
| `task:chat:update` | 1 | 20 |
| `task:checklist:add` | 1 | 18 |
| `task:checklist:item:add` | 1 | 20 |
| `task:checklist:item:complete` | 1 | 18 |
| `task:checklist:item:delete` | 1 | 19 |
| `task:checklist:item:list` | 1 | 17 |
| `task:checklist:item:renew` | 1 | 18 |
| `task:checklist:item:update` | 1 | 19 |
| `task:checklist:list` | 1 | 16 |
| `task:deadline:set` | 1 | 18 |
| `task:delete` | 1 | 18 |
| `task:fields:list` | 0 | 17 |
| `task:file:attach` | 1 | 21 |
| `task:find` | 0 | 32 |
| `task:history:list` | 1 | 18 |
| `task:list` | 0 | 32 |
| `task:participants:set` | 1 | 21 |
| `task:result:add` | 1 | 19 |
| `task:result:delete` | 1 | 19 |
| `task:result:list` | 1 | 19 |
| `task:result:update` | 1 | 20 |
| `task:show` | 1 | 17 |
| `task:time:add` | 1 | 19 |
| `task:time:delete` | 1 | 19 |
| `task:time:list` | 1 | 18 |
| `task:time:show` | 1 | 16 |
| `task:time:update` | 1 | 20 |
| `task:update` | 1 | 28 |
| `users:colleagues` | 0 | 20 |
| `users:current` | 0 | 16 |
| `users:find` | 0 | 21 |
| `users:manager` | 0 | 18 |
