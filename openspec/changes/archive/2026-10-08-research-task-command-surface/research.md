# Исследование готовых CLI и MCP для Bitrix24

Статус: исследование, проверка README/исходников; runtime сторонних проектов не проверен. Связь: [issue #10](https://github.com/mesilov/bitrix24-cli/issues/10).

## Метод и границы исследования

Проверено **2026-10-08**: поиск публичных GitHub-репозиториев по bitrix24 cli / b24-cli / bitrix24 mcp, README, package.json, releases и метаданные репозиториев; официальная документация Bitrix24. Даты ниже — последний push, а не доказательство поддержки. Установка пакетов, CI сторонних проектов, реальные операции на портале и полнота заявленного покрытия не проверялись. Числа tools/actions — заявления README, не результат протокольной проверки.

## Готовые CLI

| Проект | Охват | Состояние на дату исследования |
| --- | --- | --- |
| [polza-ai/bitrix-cli](https://github.com/polza-ai/bitrix-cli) | CRM CRUD, активности, таймлайн, JSON; webhook и готовый OAuth access token | TypeScript; MIT заявлена в package.json; 0.1.1, 3 коммита, нет GitHub releases; push 2026-04-26 |
| [poydevor/b24-cli](https://github.com/poydevor/b24-cli) | Интерактивные задачи: создание/редактирование/удаление, статусы, история, чек-листы, комментарии, время, делегирование, канбан | TypeScript, MIT; [v1.2.0](https://github.com/poydevor/b24-cli/releases/tag/v1.2.0) от 2026-03-18; README/package.json содержат старые ссылки nnolan-oss |
| [vrtalex/bitrix24-skill](https://github.com/vrtalex/bitrix24-skill) | Python CLI внутри agent skill: REST-вызовы, capability packs, webhook/OAuth, allowlist, планирование записи, аудит, идемпотентность | Unlicense; push 2026-06-08 |
| [Bayselonarrend/OpenIntegrations](https://github.com/Bayselonarrend/OpenIntegrations) | CLI oint с модулем Bitrix24: задачи, чаты, файлы, лиды/сделки, календарь; webhook и локальное приложение | OneScript, MIT; CLI-пакеты Windows/Linux; push 2026-10-06; [модуль Bitrix24](https://openintegrations.dev/docs/Instructions/Bitrix24/) |
| [matheusraull99/bitrix24-python-sdk](https://github.com/matheusraull99/bitrix24-python-sdk) | SDK + CLI: сделки, экспорт лидов, произвольный call, mock | Python, MIT; push 2026-07-24 |
| [Eurasia-Group-Kazakhstan/bitrix_tools](https://github.com/Eurasia-Group-Kazakhstan/bitrix_tools) | Отдельные скрипты экспорта контактов/товаров, удаления товаров, импорта из ClickHouse | Python 3.12; лицензия GitHub не определена; push 2026-03-19 |
| [the-hugin/bitrex](https://github.com/the-hugin/bitrex) | Экспорт компаний/контактов в CSV и raw JSON | Windows PowerShell, MIT; push 2026-06-17 |

Смежные, но не замена прикладному CLI: [user1885/bitrix-cli](https://github.com/user1885/bitrix-cli) реализует только OAuth auth; [bitrix-tools/cli](https://github.com/bitrix-tools/cli) архивирован и заменён на [chef](https://github.com/bitrix-tools/chef) для JS-расширений; [vdistortion/bitrix24-create-app](https://github.com/vdistortion/bitrix24-create-app) — архивированный генератор проектов.

## MCP: официальные варианты

1. **Работа с данными портала**: [русская инструкция](https://helpdesk.bitrix24.ru/open/26952788/) указывает https://mcp.bitrix24.tech/mcp/, [международная](https://helpdesk.bitrix24.com/open/25866707/) — https://mcp.bitrix24.com/mcp/. OAuth или токен подключения; администратор включает доступ, действия выполняются с правами пользователя. CRM, задачи, структура, приглашения, почта — по документации. Полный tools/list живого сервера не проверен; hosted MCP не заявляем open source без опубликованных исходников.
2. **Документация REST**: [официальная русская документация](https://apidocs.bitrix24.ru/ai-tools/mcp.html), [GitHub](https://github.com/bitrix24/mcp-rest-doc). Русский endpoint https://mcp-dev.bitrix24.tech/mcp; международный https://mcp-dev.bitrix24.com/mcp. Без авторизации; поиск методов/событий/статей. Не читает данные портала и не выполняет REST-вызовы.

## MCP на GitHub

| Проект | Охват и ограничения | Последний push |
| --- | --- | --- |
| [bitrix24/templates-mcp](https://github.com/bitrix24/templates-mcp) | Официальный шаблон TypeScript/Nuxt, MIT; README заявляет 29 Bitrix24 tools (27 задач + 2 users) и отдельный feedback tool. Webhook, opt-in OAuth, stdio/HTTP/Docker. CRM — дальнейшее развитие | 2026-09-21 |
| [kostikpenzin/mcp_b24](https://github.com/kostikpenzin/mcp_b24) | Node.js, MIT; README заявляет 43 tools и ~870 actions, широкий REST-охват, webhook/OAuth, stdio/HTTP. Подтверждение destructive actions отключено по умолчанию; включается BX24_CONFIRM_DESTRUCTIVE=true. В метаданных репозитория указано 41 tool — расхождение с README | 2026-08-27 |
| [paskal/bitrix24-mcp-server](https://github.com/paskal/bitrix24-mcp-server) | MIT; задачи, CRM, users/workgroups, чаты и расширения для звонков; readonly mode | 2026-10-06 |
| [kartochka/bitrix24-mcp](https://github.com/kartochka/bitrix24-mcp) | Python, MIT; контакты/сделки, поиск, изменение стадии, resources/prompts; небольшой прикладной охват | 2025-05-28 |
| [gunnit/bitrix24-mcp-server](https://github.com/gunnit/bitrix24-mcp-server) | TypeScript; MIT заявлена в README; контакты, сделки, лиды, компании, задачи, пользователи через webhook | 2025-07-16 |

## Выводы для поверхности задач

- poydevor/b24-cli — референс пользовательских сценариев задач; templates-mcp — референс декомпозиции операций и параметров; mcp_b24/OpenIntegrations — карта более широкого охвата.
- Источник истины для REST-методов и условий — [официальный обзор задач](https://apidocs.bitrix24.ru/api-reference/tasks/index.html) и отдельные страницы методов, а не список команд стороннего проекта.
- [Новая карточка задач](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-new.html) требует отдельной модели обсуждений: legacy comments нельзя без проверки выдавать за поддержку современного task chat.
- Разделить REST 1.0 (scope task) и REST 3.0 (scope tasks), несмотря на совпадение некоторых имён методов; проверить фактическое покрытие b24phpsdk из composer.lock.
- CLI остаётся полезным для shell/CI и воспроизводимых команд; собственный MCP и переход на сторонний проект пока не являются принятым решением.
- Предлагаемые команды, MVP и последующие приоритеты должны быть явно помечены как проектный вариант для ревью.


## Фиксированные исходники для поверхности задач

- [Официальный обзор задач, commit 2b00d6bd3a845f4169ccb1ff6f0755f156903f4b](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/index.md): извлечено 98 записей методов/событий, включая разные версии одинаковых имён.
- [b24phpsdk 3.7.0, commit 8ebd4c154d5557db949b0196825f347a3a6c5bf0](https://github.com/bitrix24/b24phpsdk/tree/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task): версия и commit соответствуют composer.lock базы dev 5d236a0. Прочитан установленный исходный код SDK из основного checkout; сравнивались реальные literal Core calls, а не только атрибуты/README.
- [templates-mcp README](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/README.md).
- [b24-cli README](https://github.com/poydevor/b24-cli/blob/6bd5166ec3c4b10d7579b2a7bd927710965b129f/README.md).
- [mcp_b24 README](https://github.com/kostikpenzin/mcp_b24/blob/74e9062ef04c53b6be336b778a73c5236996e015/README.md).

## Уточнения после чтения API и PHP SDK

1. **Смешанная версия SDK.** Task::get/add/update/delete, TaskChat, TaskFile, TaskAccess и схемы полей обращаются к REST 3.0. Task::list отсутствует; task()->batch->list() использует legacy API и верхний регистр фильтров. Нельзя утверждать «весь SDK legacy» или «весь блок REST 3.0».
2. **Список задач v3 имеет ограничения.** Отдельная [страница list v3](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-list-rest-v3.md) документирует filter только по id; без select возвращается лишь id. Список «мои задачи/проект/статус» предлагается строить через legacy list, явно указывая выбранный API.
3. **Обязательные поля create v3:** title, creatorId, responsibleId. README templates-mcp перечисляет только title/responsibleId как required: этот интерфейс нельзя автоматически переносить на прямой v3 REST-вызов.
4. **Участники:** [поля v3](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/fields-rest-v3.md) запрещают accomplices/auditors в add/update. Для назначения соисполнителей и наблюдателей указан legacy tasks.task.update с ACCOMPLICES/AUDITORS. Составную create + participants операцию нельзя объявлять атомарной.
5. **Комментарии:** обзор задач помечает comment methods как неактуальные для новой карточки. Более подробная [миграционная таблица](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-new.md) допускает task.commentitem.add как совместимый путь, но update/delete/getlist не работают; для нового интерфейса предлагается task chat. Legacy result.addFromComment/deleteFromComment тоже не работают в новой карточке; современный result.add/from-message/list требует v3.
6. **SDK gaps:** нет отдельных Task service-обёрток lifecycle, delegate, history, counters, зависимостей и современных result.add/update/delete/list. CoreInterface::call поддерживает явную ApiVersion; отсутствие обёртки не означает отсутствие REST API.
7. **Метаданные SDK могут расходиться с вызовом.** Userfield::getList имеет атрибут task.item.userfield.list, но реальный вызов task.item.userfield.getlist. TaskServiceBuilder помечен scope task, хотя некоторые методы вызывают v3, требующий tasks. SDK coverage в матрице основан на коде call; фактические credentials/scopes проверяются отдельно.
8. **Документация стадий содержит противоречивый пример.** Таблица movetask требует взаимоисключения before/after, а пример передаёт оба. Поверхность следует таблице параметров и отвергает такую комбинацию.
9. **stopwatch — выключение наблюдения**, а не остановка учёта времени. Канбан stage и lifecycle status, иерархия parentId и зависимости Ганта, chat message и result — разные операции.

## Уровни уверенности

- Высокая: наличие опубликованных репозиториев, конкретных страниц документации, literal REST calls в SDK закреплённой версии.
- Средняя: заявленные README функции сторонних решений, схема параметров и ограничения опубликованных REST-методов; могут зависеть от версии портала.
- Не проверено: установка npm/uvx-пакетов, текущие tools/list hosted MCP, работа портала/коробки, scopes реальных credentials, поля/лимиты/переходы на конкретном аккаунте, CI/тесты сторонних проектов.

[Поверхность команд](task-surface.md) и [машиночитаемая матрица](task-surface.json) являются предложениями. Публикация исследования не утверждает выбор стороннего проекта, гарантированную runtime-совместимость или принятый объём MVP.
