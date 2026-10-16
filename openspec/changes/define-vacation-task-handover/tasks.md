# Tasks

## 1. Трассировка и контракт

- [x] 1.1 Проверить issue #162, milestone 0.3.0 и обратную ссылку, обновить planning под отдельную команду и режим руководителя; подтвердить строгую проверку OpenSpec и ветку feature/162-task-handover от dev.

## 2. Участники и планирование

- [x] 2.1 Реализовать gateway активных участников проекта, CLI input, DI/CommandCatalog/ScopeManifest и полный preflight scan без лимита вывода; проверить unit cases прав, членства, неполной выборки, неверного ввода, self/manager и strict-rest3 zero calls; документировать маршруты/scopes.

## 3. Передача и отчёт

- [x] 3.1 Реализовать GET/UPDATE/GET каждого ID, остановку при конфликте/ошибке/неизвестном исходе/отмене, read-only dry-run и JSON/plain/human отчёт; проверить фильтрацию проекта/исполнителя/статусов, readback и сохранение подтверждённых результатов unit-тестами.
- [x] 3.2 Добавить dedicated integration happy-path с собственными fixtures и явным opt-in изолированной группы; проверить coverage harness и описать границы live-приёмки без массовых изменений на реальном проекте.

## 4. Спецификации и пользовательская документация

- [x] 4.1 Синхронизировать task-workflows (EMP-09, PM-04) и новую task-project-handover, обновить docs/task-jtbd.md (24 IDs, 8/7/9), README, командную карту и package smoke; проверить ID, ссылки, help и cli-reference-check.
- [x] 4.2 Добавить содержательный CHANGELOG Unreleased со ссылкой #162; проверить diff относительно актуальной origin/dev и сохранить результаты обязательных проверок/границы приёмки в verification.md.

## 5. Интеграционная проверка и доставка

- [x] 5.1 Выполнить unit/check/lint-all, OpenSpec strict, целевую integration проверку (skip допустим только с явным указанием отсутствия opt-in), git diff --check и verify соответствия; все фактические результаты сохранить в verification.md.
- [ ] 5.2 Синхронизировать ветку со свежей origin/dev, открыть MR в dev, выждать 5 минут, устранить review замечания, дождаться SUCCESS обязательных CI текущего head и подтвердить merge и закрытие issue.

## Workflow follow-up

- После merge архивировать change по локальному skill, сохранить main specs, обновить путь issue на архивный, доставить архив через отдельный MR в dev с changelog и проверенным merge.
- Остановить task-scoped Docker-окружение, проверить нужные ignored/untracked файлы и локальные коммиты, удалить только worktree issue #162 без --force.
