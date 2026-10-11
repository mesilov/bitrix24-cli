# Design

## Context

Мотивация — в [proposal.md](proposal.md); контракт — в [cli-experience delta](specs/cli-experience/spec.md). Проверка кода 11.10.2026 на dev b53178e нашла `addOption('project')` в AddTaskCommand/UpdateTaskCommand/ListTaskCommand и mapping project → groupId в TaskInputMapper для patch/list. task:list использует JSON groupId filters через --where/--where-file. task:find принимает только title/limit/all/max-scan; JSON filters и отдельного project flag там нет. По уточнению пользователя добавить --group-id в task:find, сохранив остальные options.

## Goals / Non-Goals

**Goals:** одно имя явного флага группы, одинаковая терминология config/JSON/API, полное удаление старого option и сохранение поведения успешных вызовов.

**Non-Goals:** новая модель фильтрации, замена REST version/routes, новые JSON/filter options task:find сверх --group-id, реализация profile-local defaults #19, изменение Docker Compose --project-name или исторических записей.

## Decisions

### Переименование в объявлениях и общем mapper

Заменить option project на group-id в трёх найденных командах, добавить group-id в task:find и ключах TaskInputMapper. Сохранить преобразование в groupId и существующую ID validation. JSON field/filter groupId не переименовывается. Учитывать option declarations, а не только текстовые вхождения --project: старое имя в PHP хранится без префикса.

### Фильтр группы для task:find

Добавить --group-id с тем же положительным integer validation. Передать groupId в существующие локальные predicates TaskQuery; общий scanner выберет groupId и применит его вместе с Unicode title substring. REST3 поддерживает серверный filter только id: groupId остаётся локальным. Сохранить id/title output, scope, scan budget, limit/all и partial exit 3. Без флага поиск работает как прежде.

### Удаление без периода alias

Пользователь явно потребовал отдельную задачу на удаление. Deprecated alias не оставлять: это сохранило бы два имени и не выполнило критерий единой поверхности. Неизвестный старый option должен завершаться до загрузки подключения/API, с usage exit 2. Проверить реальный subprocess parser и fake transport: unit mapper сам по себе не доказывает отсутствие принятого alias.

### Актуальная документация и исторические данные

Обновить help/completion, README/docs, активные examples и tests/fixtures. В MODIFIED delta сохранить все прежние сценарии редактирования/фильтрации с новым именем. Остаточные упоминания старого флага допускаются только для migration instructions, отрицательных тестов и неизменяемой истории. Поиск должен различать точный --project и Docker --project-name.

### Связь с конфигурацией #19

Реализовать флаг независимо от ещё не реализованного storage: явный --group-id работает с текущими env credentials. В #19 profile groupId остаётся опциональным default только task:add, а явный флаг/JSON имеет приоритет. Проверка новых примеров #19 требует реализации этого change; не добавлять defaults в list/update внутри #27.

## Risks / Trade-offs

- Scripts со старым флагом перестанут работать → явно обозначить breaking change и дать замену в migration notes для 0.1.0.
- Help или completion могут сохранить старое имя → проверить реальные command definitions/help/completion и ограниченный остаточный поиск.
- Ошибка переименования меняет фильтрацию вместо имени → сравнить payload groupId и результаты controlled fixture до/после, сохраняя scan/partial contracts.
- Массовая текстовая замена затронет Compose identity или архивы → менять только declarations/mapper и актуальные CLI examples, проверять diff.

## Migration Plan

1. Реализовать переименование в отдельной ветке feature/27-unify-group-id-option и обновить docs/specs/tests.
2. Пройти unit/subprocess проверки нового и удалённого флагов и проектные gates; выполнить OpenSpec verify.
3. Сообщить пользователям замену `--project ID` на `--group-id ID` без изменения groupId payload; публиковать через PR в dev.
4. После реализации и verification архивировать change и обновить фактический путь в #27. При откате кода вернуть связанную документацию; конфигурация groupId и API данные миграции не требуют.
