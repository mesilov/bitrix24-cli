# Verification: add-webhook-scope-drift-detection

Дата: 2026-10-15. Issue: https://github.com/mesilov/bitrix24-cli/issues/149, milestone 0.3.0. Исходная dev: 289990b. Проверка через openspec-verify-change, schema spec-driven.

## Completeness

8/8 задач выполнены. Опубликован MR https://github.com/mesilov/bitrix24-cli/pull/150 в dev, milestone 0.3.0, label enhancement. Issue #149 содержит согласованное решение, путь change и ссылки на опубликованные артефакты; proposal содержит обратную ссылку. Все 8 требований и 14 сценариев имеют реализацию/контролируемые проверки. Change остаётся активным до доставки через review/merge.

## Correctness

| Требование | Реализация | Проверка |
| --- | --- | --- |
| Versioned route scope requirements | ScopeManifest::required/revision/missing | ScopeManifestAndStateTest: каталог, неизвестный маршрут, альтернативы user/task, conditional disk/im |
| Independent protected connection state | ScopeStateStore, ScopeValidator::key | Независимые config/profile/env/file/rotated credentials; private file, symlink, malformed JSON/version/types/dates; два writer process и один scope probe для двух команд |
| Daily lazy refresh and manifest invalidation | ScopeValidator::check/cached, TTL=86400 | 86399/86400 секунд, manifest смена, версия приложения без смены manifest |
| Actual webhook scope discovery | SDK call scope с [], проверка raw JSON и validScopes | Без full, scalar/object/null/bool не превращаются в snapshot, успешный [] отличается от unknown |
| Failures preserve successful evidence and back off | ScopeValidator, BACKOFF=300 | Transport/invalid result, сохранение scopes/даты, граница 299/300 секунд, manual override |
| Only affected operations are blocked | ScopeCheckingTransport::beforeCommand перед prepare | Missing im блокирует chat до tasks.task.get; task:list работает; optional disk не блокирует file-id; object denial сохраняется, write не повторяется |
| Explicit connection scope diagnostics | CheckConnectionCommand, ScopeCheckingTransport::checkConnection, ResultPresenter | Force check, NAME/selectors, human/plain/JSON, fixture secrets отсутствуют, ограничение conditional commands обозначено |
| Preserve offline and API policy boundaries | CommandRunner, ApiPolicyGuard, read-only cache branch | Help/version/list/local/dry-run без state/API; strict-rest3 не посылает REST1 и использует свежий snapshot |

## Coherence

Следует существующим DI, SDK, RuntimeState, Failure и FileProtection patterns. Production preflight является частью декоратора ApiTransport; application unit tests с заменой transport изолированы, новые command tests используют реальную DI/SDK цепочку и MockHttpClient. State и config имеют отдельные locks, config bytes не меняются. API field `full` не передаётся. Raw granted scope codes не переименовываются.

## Локальная проверка

- Полный unit suite после исправлений review: **847 tests, 13436 assertions**, pass (PHP 8.4.26, Linux container).
- После уточнения обхода JSON object повторена целевая матрица: **21 tests, 185 assertions**, pass; PHPStan повторно pass.
- Composer validate --strict, platform requirements, лицензии, PHP-CS-Fixer, PHPStan и Rector: pass.
- Синтаксис 349 PHP-файлов src/config/tests и bin/b24cli/bin/console: pass.
- CLI startup и generated CLI reference: pass, **57 публичных команд**.
- OpenSpec validate add-webhook-scope-drift-detection --strict: pass.
- Compose forwarding: pass, 4 fixtures, readonly overlays.
- Config schema: pass, 146 fixtures, 3 примера, 8 дополнительных runtime checks. Doctor input schema: pass, 2 примера и 11 negative cases. Использован закреплённый jsonschema 4.25.1 в отдельном временном venv.

Исходный Make composer-install не мог создать Docker network из-за исчерпанных address pools. Проверки выполнены тем же существующим PHP runtime через отдельные docker run без удаления чужих сетей; composer install использовал host network, проверки — network none. Source/lock dependencies не менялись.

## Границы и замечания

- Живой портал **не проверен**. Новый read-only integration scenario: 1 skipped / 0 assertions, поскольку в изолированном worktree нет webhook. Это не pass портального поведения.
- Нативные Windows ACL/rename и macOS execution нового state **не проверены** в этом запуске. Используется существующий FileProtection, но это не новое native acceptance.
- Документация противоречива по task/tasks: консервативная группа описана в design и docs; код не утверждает эквивалентность и рекомендует task.
- Проверка scope не доказывает объектные права/тариф; TTL сохраняет окно до суток. Manual check обновляет немедленно.
- Найденных критических расхождений в проверенных требованиях нет; live/native checks остаются not verified. Архивирование, merge и релиз не выполнены. CI окончательного SHA отслеживается в MR отдельно от локальных результатов.

## Исправления по review MR #150

- [Changelog](https://github.com/mesilov/bitrix24-cli/pull/150#discussion_r4282370305): добавлена содержательная запись в Unreleased с issue #149; проверяется собственный diff относительно актуальной dev.
- [Диагностика](https://github.com/mesilov/bitrix24-cli/pull/150#discussion_r4282370310): failure presenter сохраняет полный scope report в stderr human/plain; B24Application передаёт plain-режим. JSON error envelope сохранён. Регрессия проверяет точные grantedScopes, timestamp, manifest revision, potentiallyAffectedCommands, connection и пустой stdout при human/plain ошибке.
- [Пример](https://github.com/mesilov/bitrix24-cli/pull/150#discussion_r4282370327): TaskUsage не добавляет dry-run к connection:check, карта сгенерирована повторно; parser/help tests проверяют реальный диагностический пример. Примеры остальных команд сохраняют прежний dry-run.
- В docs/task-jtbd.md актуализированы общие границы прав для существующих сценариев, ссылки на runtime/tests и отсутствие live-приёмки; состав 22 JTBD не изменён.
- Повторные PHPStan, PHP-CS-Fixer и Rector: pass. Generated CLI reference: 57 команд, pass. OpenSpec --all --strict: 25 passed, 0 failed.
- Закрытие threads, финальная проверка reviews и CI выполняются после публикации исправлений; статус подтверждается в GitHub.
