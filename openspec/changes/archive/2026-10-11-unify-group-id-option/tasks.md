# Tasks

## 1. Canonical group identifier

- [x] 1.1 Переименовать option project в group-id в task:add/task:update/task:list и общем TaskInputMapper, сохранив groupId payload/filter и положительный integer ID; проверить add/update payload и list selection controlled fixture тестами, обновить command help/examples.
- [x] 1.4 Добавить task:find --group-id как локальный фильтр вместе с title; проверить разные группы/подстроки, отсутствие флага, positive integer validation, limit/all, scan cap/exit 3 и id/title output; обновить help/README и актуальные CLI candidates.
- [x] 1.2 Полностью удалить обработку --project без alias/fallback; добавить subprocess negative tests для каждой прежней команды, старого флага отдельно и вместе с --group-id: exit 2 и ноль API calls; дать migration note со сменой имени.
- [x] 1.3 Проверить invalid/empty/zero/negative/absent ID и advanced JSON groupId/fields-file/JSON list filters и неизменность title search task:find; целевые тесты подтверждают прежнюю validation и несовместимость advanced input с field flags, с новым --group-id task:find.

## 2. Согласованность поверхности

- [x] 2.1 Обновить актуальные README/docs, completion, examples и fixtures, связанные активные OpenSpec примеры; проверить реальные help/completion четырёх команд и поиск option declarations/mapper keys, остаточные --project допускаются только в migration/negative tests/history; Compose --project-name не изменяется.
- [x] 2.2 Согласовать примеры #19/change add-cross-platform-connection-profiles с canonical --group-id без повторной реализации defaults; после доступности #19 проверить explicit groupId > profile default, отдельно зафиксировать зависимость, если storage ещё не реализован.

## 3. Интеграционная проверка

- [x] 3.1 Выполнить целевые unit/subprocess проверки, make check, make unit, make lint-all, strict OpenSpec validation и diff check; записать точные результаты в verification.md, не выдавать fake transport за live portal proof.
- [x] 3.2 Выполнить openspec-verify-change, проверить #27 ↔ proposal, milestone 0.1.0 и категории; устранить найденные несоответствия и подготовить результат для PR в dev.

## Workflow follow-up

- Архивировать после реализации и проверки соответствия, синхронизировать cli-experience и обновить фактический archive path в issue #27.
- Довести разрешённую пользователем поставку через PR feature → dev и PR dev → main; подтвердить CI и merged state обоих PR.
