# Spec Delta

## Purpose

Позволяет фиксировать и поддерживать результаты работы в выбранной задаче Bitrix24 через CLI, сохраняя самостоятельную сущность результата и проверяемую принадлежность задаче.

## ADDED Requirements

### Requirement: Native task result creation and editing
CLI SHALL предоставлять `task:result:add TASK_ID` и `task:result:update TASK_ID --result ID` с непустым `--text` либо `--text-file PATH|-`. Создание SHALL отправлять REST3 `fields.taskId` и `fields.text`; изменение — `id` и `fields.text`. Подтверждение SHALL содержать совпадающие `taskId`, положительный ID результата и подтверждённую операцию.

#### Scenario: Text from stdin
- **WHEN** сотрудник добавляет результат через `--text-file -`
- **THEN** текст stdin сохраняется в отдельном результате выбранной задачи, а CLI возвращает его ID
- **AND** CLI не выполняет команду чата или изменение статуса задачи

#### Scenario: Invalid or conflicting input
- **WHEN** ID неположительный, текст пустой или одновременно заданы text и text-file
- **THEN** CLI возвращает usage-error с кодом 2 до API-вызовов

### Requirement: Task-scoped result listing
CLI SHALL предоставлять `task:result:list TASK_ID` через REST3 с обязательным filter по `taskId`, стабильным order `id ASC` и пагинацией. Вывод SHALL сохранять API имена `id`, `taskId`, `text`, `authorId`, `createdAt`, `updatedAt`, `status`, `fileIds`, `rights`, `messageId`. `--limit` SHALL ограничивать вывод (default 50), `--all` снимать его, `--max-scan` ограничивать обход (default 10000).

#### Scenario: Several pages and empty task
- **WHEN** результаты занимают несколько страниц или список пуст
- **THEN** CLI обходит страницы, удаляет дубликаты по id, возвращает data.items array и meta.complete
- **AND** plain выводит колонки id, taskId, authorId, createdAt, status, text

#### Scenario: Incomplete enumeration
- **WHEN** исчерпан max-scan, пагинация не продвигается или последующая страница недоступна
- **THEN** доступные результаты сохраняются, meta.complete=false, причина указана, exit code равен 3

### Requirement: Verified task result binding
Перед update/delete CLI SHALL читать результат через REST3 list с filter по `taskId` и `id`. Отсутствующий либо чужой результат SHALL приводить к resource-binding-unverified с кодом 4 без записи. `--force` SHALL отключать только диалог удаления. Ответ update SHALL подтверждать совпадающие id/taskId, а delete — REST3 result.result=true.

#### Scenario: Foreign result
- **WHEN** пользователь указывает результат другой задачи при update либо delete
- **THEN** запись не выполняется даже при --force

### Requirement: Common runtime contract for results
Команды результатов SHALL использовать общий API policy, JSON envelope, human/plain вывод, dry-run и обработку ошибок. Все маршруты SHALL оставаться REST3; потеря или повреждение ответа после записи SHALL сохранять outcomeUnknown без автоматического повтора.

#### Scenario: Mutation preview
- **WHEN** пользователь вызывает add/update/delete с --dry-run
- **THEN** CLI возвращает план без записи; update/delete допускают только проверочное чтение принадлежности
- **AND** strict-rest3 разрешает эти команды

#### Scenario: Unconfirmed mutation
- **WHEN** ответ создания/изменения имеет повреждённый item либо другой id/taskId, или delete не подтверждён boolean true
- **THEN** CLI не объявляет успех, возвращает api-error с outcomeUnknown=true и не повторяет запись
