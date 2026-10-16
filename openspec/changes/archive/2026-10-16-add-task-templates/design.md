# Design

## Context

Сценарий согласован пользователем 16.10.2026. Реализация следует CommandRunner → application service → port → REST adapter. Официальный documentation MCP доступен; effective config проверен из worktree. Запросы MCP подтверждают документацию, не наличие методов и права на конкретном портале.

## Goals / Non-Goals

Предоставить пять команд с точными REST 1.0 полями и проверяемыми результатами. Не подменять native-шаблоны локальным копированием полей задачи. Не добавлять list/find, создание задач, расписания повторений, custom fields и чек-листы в этой поставке.

## Decisions

- `show TEMPLATE_ID` вызывает `tasks.template.get` с integer `templateId`; `fields:list` вызывает `tasks.template.fields` без параметров. Поля ответа сохраняются без переименования и десериализации PHP-строк.
- `add/update` принимают `--fields JSON` либо `--fields-file PATH|-` с нативными ключами. Для обычного ввода есть `--title`, `--description`, `--creator`, `--responsible`, `--group-id`. Смешивание источников одного поля отклоняется. Profile default.task не применяется к шаблонам.
- Первая реализация записывает TITLE, DESCRIPTION, CREATED_BY, RESPONSIBLE_ID, GROUP_ID, PARENT_ID, ACCOMPLICES, AUDITORS, ALLOW_CHANGE_DEADLINE, ALLOW_TIME_TRACKING, TASK_CONTROL, ADD_IN_REPORT. TITLE непустой; IDs — JSON integers (положительные, GROUP_ID/PARENT_ID допускают 0); участники — списки положительных integers; переключатели — строки Y/N. Для add обязательны TITLE, CREATED_BY, RESPONSIBLE_ID. Неизвестные поля и REST3 aliases отклоняются до API. Update отправляет только явные поля.
- Все маршруты REST 1.0, scope task (существующая группа task|tasks). strict-rest3 отклоняется до API, включая dry-run. Add/update dry-run полностью offline; delete dry-run читает целевой шаблон для проверки ID и отображения названия. Удаление в automation требует --force по общему контракту.
- SDK оборачивает scalar result в массив: add должен вернуть положительный integer ID, update — boolean true. 0/false на add/update и некорректные ответы не считаются подтверждением записи; неопределённый результат не повторяется автоматически.
- Delete предварительно читает шаблон. Документированный результат delete=false не доказывает удаление: возвращается partial (exit 3), operation.confirmed=false, complete=false. Последующее чтение показывает visibleAfter, но false у get также означает отсутствие прав. Нельзя заменять эту неоднозначность утверждением об удалении. Happy-path integration проверяет dry-run и реальное частичное подтверждение отдельно.
- JSON: data.template / data.fields / data.operation {templateId, action, changedFields, confirmed}. Plain show/fields: field,value (поля отсортированы); mutations: templateId,action,changedFields,confirmed; delete добавляет visibleAfter (true/false/unknown, JSON сохраняет null). План сохраняет общий формат и templateId в steps; taskId=null.

## Official sources and verification boundary

Проверено 16.10.2026 через bitrix-search, затем method/article details:

- https://apidocs.bitrix24.ru/api-reference/tasks/template/index.html — пять основных методов, scope task; list/instantiate отсутствуют в обзоре.
- https://apidocs.bitrix24.ru/api-reference/tasks/template/tasks-template-get.html — templateId, result object либо false при отсутствии шаблона/прав.
- https://apidocs.bitrix24.ru/api-reference/tasks/template/tasks-template-fields.html — без параметров, объект metadata.
- https://apidocs.bitrix24.ru/api-reference/tasks/template/tasks-template-add.html — fields, обязательные TITLE/CREATED_BY/RESPONSIBLE_ID, положительный ID или 0.
- https://apidocs.bitrix24.ru/api-reference/tasks/template/tasks-template-update.html — templateId/fields, boolean true или false.
- https://apidocs.bitrix24.ru/api-reference/tasks/template/tasks-template-delete.html — false независимо от подтверждения удаления; нужны проверка прав и осторожная интерпретация.
- https://apidocs.bitrix24.ru/api-reference/tasks/template/fields.html — нативные имена и типы. PRIORITY и относительные сроки имеют расхождения между страницами; они не входят в начальный набор записи.

Live portal acceptance, полная матрица прав и standalone binaries проверяются отдельно от локальных тестов и CI.

## Risks / Trade-offs

- Неоднозначный delete response → частичный результат и совет проверить портал перед повтором.
- get=false не различает отсутствие/нет прав → resource-unavailable с exit 4 без записи.
- Неподтверждённый набор сложных полей → явный allowlist без молчаливой потери или преобразования данных.
