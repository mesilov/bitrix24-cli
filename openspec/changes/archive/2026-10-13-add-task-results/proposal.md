# Proposal

## Why

CLI должен фиксировать и читать результат работы как отдельную сущность Bitrix24. Сейчас пользователю приходится обращаться к REST/MCP, а сообщение в чате или завершение задачи не заменяет результат.

## What Changes

- Добавить `task:result:add`, `task:result:list`, `task:result:update`, `task:result:delete` через REST 3.0.
- Поддержать текст из аргумента, файла и stdin; dry-run, подтверждение удаления, JSON и plain по существующему контракту.
- Проверять принадлежность результата выбранной задаче перед update/delete.
- Добавить отдельные JTBD работы с результатами для руководителя проекта, аккаунт менеджера и сотрудника, README и проверяемое покрытие.
- Выделить шаблоны задач в отдельное issue и change `add-task-templates`; оставить их реализацию отложенной по указанию пользователя.

## Capabilities

### New Capabilities

- `task-results`: создание, чтение, редактирование и удаление результатов задачи.

### Modified Capabilities

- `task-workflows`: самостоятельные сценарии работы с результатами для трёх ролей.
- `task-console-runtime`: каталог из 34 task-команд после добавления четырёх result-команд.
- `task-mvp-scope`: явный post-MVP допуск результатов в 0.2.0 при сохранении исторической границы 0.1.0.

## Impact

CommandCatalog, DI, Console commands/input/output, Application service/requests/port, REST3 adapter, unit и интеграционные тесты, README и JTBD-матрица. Новые зависимости не нужны. Не включаются создание результата из сообщения, загрузка файлов результата, шаблоны, завершение и приёмка задачи.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/61 — четыре операции с результатами задачи и JTBD; milestone 0.2.0.
