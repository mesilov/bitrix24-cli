# Tasks

## 1. Манифест и состояние

- [x] 1.1 Реализовать явный ScopeManifest с hash, альтернативными группами и зависимостями Route; проверить покрытие каталога и optional disk/im в unit-тестах, документировать источники.
- [x] 1.2 Реализовать защищённый versioned state.json с атомарной записью и отдельным lock; проверить malformed/permissions/symlink, независимость credentials/config и параллельные записи, описать формат.

## 2. Проверка и выполнение

- [x] 2.1 Реализовать TTL 86400, backoff 300, manifest/credential invalidation, сохранение успешного снимка и unknown/stale; проверить часы/границы/сбои/ручное обновление в unit-тестах.
- [x] 2.2 Подключить production preflight до prepare, scope-missing/exit4 только выбранного плана и точный insufficient_scope без retry; проверить production MockHttpClient сценарии missing im, независимый task:list, optional disk, object denial, dry-run/offline и strict-rest3.
- [x] 2.3 Добавить connection:check и human/plain/JSON диагностику; проверить принудительный refresh, selectors, secrets и обновить CLI reference/документацию.

## 3. Общая проверка и ревью

- [x] 3.1 Выполнить полные unit tests, Composer/syntax/CLI checks, quality linters и strict OpenSpec validation; сохранить результаты и границы portal/native acceptance.
- [x] 3.2 Проверить соответствие issue ↔ proposal/design/spec/tasks и реализации через openspec-verify-change; опубликовать ветку и MR в dev с проверенными milestone/категорией и ссылками.

## Workflow follow-up

- После подтверждённого merge/закрытия issue архивировать change, синхронизировать spec, доставить архив в dev через MR и обновить путь issue.
- Сохранить необходимые файлы и удалить только worktree этой задачи после доставки архива.
