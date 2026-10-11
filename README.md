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

Разработка, подключение и сборка: [руководство](docs/development.md).
