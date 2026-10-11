# bitrix24-cli

CLI для работы с задачами Битрикс24.

[![Allowed licenses](https://github.com/mesilov/bitrix24-cli/actions/workflows/license-check.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/license-check.yml?query=branch%3Adev+event%3Apush)
[![PHP-CS-Fixer](https://github.com/mesilov/bitrix24-cli/actions/workflows/php-cs-fixer.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/php-cs-fixer.yml?query=branch%3Adev+event%3Apush)
[![PHPStan](https://github.com/mesilov/bitrix24-cli/actions/workflows/phpstan.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/phpstan.yml?query=branch%3Adev+event%3Apush)
[![Rector](https://github.com/mesilov/bitrix24-cli/actions/workflows/rector.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/rector.yml?query=branch%3Adev+event%3Apush)

## Скачать

Публичного релиза пока нет. Ниже — предварительные бинарники [последней успешной CI-сборки `a7024b1` от 11.10.2026](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407).

| Платформа | Архитектура | Скачать архив CI |
| --- | --- | --- |
| Linux | amd64 | [Скачать](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407/artifacts/11706600824) |
| macOS | arm64 | [Скачать](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407/artifacts/11706535808) |
| Windows | amd64 | [Скачать](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407/artifacts/11706070836) |

[Установочные архивы `.tar.gz` / `.zip` и `SHA256SUMS`](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407/artifacts/11706590395).
Для скачивания CI artifacts нужен вход в GitHub; срок хранения — 90 дней (эти сборки доступны до 09.01.2027).
[Новые сборки](https://github.com/mesilov/bitrix24-cli/actions/workflows/package-static.yml?query=branch%3Adev).

### Первый запуск

Проверьте checksum и распакуйте архив по [инструкции установки](build/README.md#установка).
В терминале перейдите в каталог с распакованным файлом. На macOS и Linux:

```sh
chmod +x ./b24cli
./b24cli --version
./b24cli --help
```

`./b24cli` запускает файл из текущего каталога. Команда `b24cli` ищется в PATH:
скачивание и распаковка не добавляют её туда, поэтому zsh может ответить
`command not found`. Чтобы вызывать `b24cli` из любого каталога, выполните
[установку для macOS/zsh](build/README.md#macos-arm64-zsh) или своей платформы.
На Windows первый запуск — `./b24cli/b24cli.exe --version` по инструкции установки.
Для скачанного macOS development artifact может потребоваться разрешить конкретный
`b24cli` через «Конфиденциальность и безопасность → Всё равно открыть» после первой
попытки запуска; см. [инструкцию macOS](build/README.md#macos-arm64-zsh).
Ad-hoc подпись проверяет целостность, но Developer ID/notarization пока не выполняются.

## CLI-команды

| Команда | Назначение |
| --- | --- |
| `task:add` | Создать задачу |
| `task:show` | Показать карточку задачи |
| `task:list` | Показать доступные задачи |
| `task:find` | Найти задачи по фрагменту заголовка |
| `task:update` | Изменить явные поля задачи |
| `task:delete` | Удалить задачу |
| `task:assign` | Сменить ответственного |
| `task:deadline:set` | Изменить срок задачи |
| `task:fields:list` | Показать поля задачи |
| `task:access:show` | Показать права текущего пользователя |
| `task:participants:set` | Заменить выбранные роли участников |
| `task:history:list` | Показать историю изменений задачи |
| `task:file:attach` | Прикрепить существующий файл Диска |
| `task:chat:delete` | Удалить сообщение чата задачи |
| `task:chat:list` | Показать сообщения чата задачи |
| `task:chat:send` | Отправить сообщение в чат задачи |
| `task:chat:update` | Изменить сообщение чата задачи |
| `task:checklist:add` | Создать чек-лист |
| `task:checklist:item:add` | Добавить пункт чек-листа |
| `task:checklist:item:complete` | Выполнить пункт чек-листа |
| `task:checklist:item:delete` | Удалить пункт с дочерними пунктами |
| `task:checklist:item:list` | Показать пункты чек-листа |
| `task:checklist:item:renew` | Возобновить пункт чек-листа |
| `task:checklist:item:update` | Изменить заголовок пункта |
| `task:checklist:list` | Показать чек-листы |
| `task:time:add` | Добавить запись времени |
| `task:time:delete` | Удалить запись времени |
| `task:time:list` | Показать записи времени |
| `task:time:show` | Показать контекст учёта времени |
| `task:time:update` | Исправить запись времени |
| `list` | Показать список команд |
| `help` | Показать справку команды |
| `completion` | Сгенерировать автодополнение для shell |

Справка по параметрам и примерам: `b24cli help task:show`.

Группа/проект задаётся положительным целым ID:

```sh
b24cli task:add --title "Договор" --creator 1 --responsible 2 --group-id 42
b24cli task:update 123 --group-id 42
b24cli task:list --group-id 42 --json
b24cli task:find --title "договор" --group-id 42 --json
```

Миграция CLI для 0.1.0: замените `--project ID` на `--group-id ID` в командах
`task:add`, `task:update` и `task:list`. Старое имя удалено без alias: его передача,
в том числе вместе с новым флагом, возвращает exit 2 до API-запроса. JSON и REST 3.0
сохраняют `groupId`; для advanced input используйте `--fields '{"groupId":42}'`,
для JSON-фильтра списка — `task:list --where '{"groupId":42}'`.
Флаг и JSON-поля нельзя смешивать. В `task:list` и `task:find` фильтр группы
выполняется локально при обходе доступных задач; `--max-scan` ограничивает обход,
`--limit` — число результатов. `task:find` совмещает `--group-id` с поиском
по заголовку без учёта регистра; без флага ищет во всех доступных группах.
Значения по умолчанию из профилей разрабатываются отдельно в [#19](https://github.com/mesilov/bitrix24-cli/issues/19).
Предварительные бинарники выше собраны до этой миграции; новое имя доступно после пересборки CLI.

Разработка, подключение и сборка: [руководство](docs/development.md).
