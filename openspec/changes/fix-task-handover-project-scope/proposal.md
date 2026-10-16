# Proposal

## Why

Live EMP-09 на dev 0caf9db блокируется ошибкой scope: manifest требует `sonet`, хотя портал возвращает `sonet_group` и разрешает `sonet_group.user.get`. Все пять целей дают exit 4 до мутаций.

## What Changes

- Требовать фактический scope `sonet_group` для чтения участников проекта.
- Обновить контракт scope, help, документацию и JTBD; сохранить strict-rest3 и action permission gates.
- Обработать подтверждённый live-сброс статуса в pending после назначения: достичь явной цели либо восстановить исходный статус штатными действиями. Другие неожиданные изменения по-прежнему останавливают пакет.
- Повторить live-матрицу фильтра, исключений и всех пяти целей с независимым чтением и очисткой fixtures.

## Capabilities

### New Capabilities
Нет.

### Modified Capabilities
- `task-project-handover`: фактическое разрешение состава проекта и обработка серверного сброса статуса после назначения.

## Impact
ScopeManifest, TaskHandoverService (routes/preview/verification), unit и integration tests, help/docs/JTBD, live evidence. Новых API и зависимостей нет.

## GitHub issues
- [#170](https://github.com/mesilov/bitrix24-cli/issues/170), milestone 0.3.0; исправление блокера приёмки #165.
