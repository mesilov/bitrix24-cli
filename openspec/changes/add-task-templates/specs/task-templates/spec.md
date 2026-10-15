# Spec Delta

## Purpose

Позволяет читать шаблоны типовых задач и сопровождать их через CLI с точными полями Bitrix24 REST API и явными границами подтверждения результата.

## ADDED Requirements

### Requirement: Read template and field metadata
CLI SHALL предоставлять task:template:show TEMPLATE_ID и task:template:fields:list через REST 1.0, сохраняя нативные имена и значения полей. ID SHALL быть положительным integer. Отсутствие или недоступность шаблона SHALL завершаться exit 4.

#### Scenario: Read an accessible template
- **WHEN** пользователь читает доступный TEMPLATE_ID
- **THEN** CLI передаёт templateId в tasks.template.get и возвращает data.template с исходными ключами

#### Scenario: Template is unavailable
- **WHEN** get возвращает false или ID ответа не совпадает
- **THEN** CLI не выдаёт успешную карточку и не выполняет запись

#### Scenario: Inspect native metadata
- **WHEN** пользователь вызывает fields:list
- **THEN** tasks.template.fields вызывается без параметров, data.fields сохраняет metadata

### Requirement: Create and update templates with native fields
CLI SHALL предоставлять task:template:add и task:template:update TEMPLATE_ID с --fields либо --fields-file PATH|- и обычными флагами. Add SHALL требовать TITLE, CREATED_BY, RESPONSIBLE_ID. Update SHALL отправлять только явные поля. Неверные имена, типы, пустой update и дубли источников SHALL отклоняться до API.

#### Scenario: Create a template
- **WHEN** переданы TITLE, CREATED_BY, RESPONSIBLE_ID правильных типов
- **THEN** tasks.template.add получает fields и положительный ID возвращается как operation.templateId

#### Scenario: Sparse update
- **WHEN** передан только --description
- **THEN** tasks.template.update получает templateId и только fields.DESCRIPTION; только true подтверждает изменение

#### Scenario: Validate native contract
- **WHEN** передан REST3 alias creatorId, неизвестный ключ, неправильный Y/N или строковый JSON ID
- **THEN** CLI завершает работу с exit 2 без API-вызовов

#### Scenario: Files and stdin
- **WHEN** fields переданы через файл или перенаправленный stdin
- **THEN** применяются те же правила проверки и точный payload, что для inline JSON

### Requirement: Report ambiguous template deletion
CLI SHALL предоставлять task:template:delete TEMPLATE_ID, читать цель до записи и требовать --force в automation. Документированный delete=false SHALL возвращать exit 3, confirmed=false, complete=false и visibleAfter, сохраняя неоднозначность get=false. Неопределённые записи SHALL не повторяться автоматически.

#### Scenario: Inspect and request deletion
- **WHEN** шаблон был доступен и delete вернул false
- **THEN** CLI повторно читает цель, сообщает её наблюдаемую доступность и частичное подтверждение без заявления об успешном удалении

#### Scenario: Protect deletion in automation
- **WHEN** реальное удаление вызвано без --force в automation
- **THEN** CLI возвращает exit 2 без API-вызовов

### Requirement: Apply shared policy output and dry run contracts
Пять команд SHALL поддерживать human, JSON и plain. Они SHALL объявлять REST 1.0 routes и требования task scope; strict-rest3 SHALL отклонять их без API. Add/update dry-run SHALL быть offline; delete dry-run SHALL только читать цель. Формат плана SHALL отличать templateId от taskId.

#### Scenario: Offline mutation plan
- **WHEN** add/update вызваны с --dry-run
- **THEN** CLI выдаёт проверенный план с templateId и исходными fields без API и записи

#### Scenario: Read only deletion plan
- **WHEN** delete вызван с --dry-run
- **THEN** выполняется только tasks.template.get и план содержит выбранный templateId

#### Scenario: Restrict API policy and permissions
- **WHEN** выбрана strict-rest3 или подтверждена нехватка task scope
- **THEN** операция блокируется до вызова template API с exit 4

#### Scenario: Stable machine output
- **WHEN** пользователь выбирает JSON или plain
- **THEN** JSON содержит data/meta/error; plain экранирует tab/newline/backslash и не содержит ANSI; human/plain delete дополнительно показывают visibleAfter=true/false/unknown, не меняя столбцы add/update

### Requirement: Maintain recurring work templates JTBD
JTBD PM-08 SHALL описывать сопровождение шаблонов типовых задач руководителем проекта: читать настройки, создавать, изменять и запрашивать удаление. Документация SHALL связывать пять команд с отдельными integration tests, сохранять границы реализации и live-проверки и не обещать создание задач по шаблону.

#### Scenario: Inspect scenario coverage
- **WHEN** пользователь читает docs/task-jtbd.md
- **THEN** PM-08 содержит команды, тесты и ограничение неоднозначного подтверждения удаления
