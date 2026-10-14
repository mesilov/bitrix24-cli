# Design

## Context

См. proposal.md. Исходный `package-static.yml` содержит только workflow_dispatch и вызывает `package-platforms.yml` с github.sha. Общая matrix принимает release_version. Production workflow требует тег в истории main и публикует автоматически; change отделяет от него numbered RC tags.

## Goals / Non-Goals

**Goals:** стабильная ветка для осознанно выбранных dev snapshots, три платформы и полный архив для приёмки до релиза.

**Non-Goals:** автоматическая упаковка каждого dev push, автоматическое создание tags/promotion, новая публичная версия, изменение API/application, release publication или увеличение платных лимитов.

## Decisions

1. Добавить push.branches=[builds] и numbered RC tag trigger в существующий caller. Сохранить workflow_dispatch и concurrency по workflow/ref с cancel-in-progress=true. Переиспользовать builders без дублирующей matrix; branch promotion и RC tagging остаются ручным выбором мейнтейнера.
2. Development identity остаётся dev-<полный SHA>, включая merge commit в builds. Артефакты фиксируют именно builds HEAD, а не изменяющийся dev tip. Итоговый 0.2.0 SHA выбирается отдельно после #121. Не переименовывать уже собранные бинарники в 0.2.0.
3. В upload-artifact шагах общей matrix использовать builds/RC ref для retention 7 дней у диагностики и 30 дней у b24cli-release-assets; для остальных ref сохранить default через retention-days=0. Будущую публикацию этих intermediate bytes не добавлять.
4. Bootstrap: создать builds из актуального dev с этим CI change, без force; реализацию отправить PR в dev по правилам репозитория. Дальше обновлять builds из dev fast-forward или обычным merge, сохраняя историю; не вливать builds обратно в dev/main. В dev change остаётся активным до review/delivery; #109 остаётся открытым как release issue.
5. YAML/events/permissions/retention проверяются локально; живой push builds подтверждает trigger и matrix. Native evidence ограничено конкретным run SHA. Workflow не имеет contents:write или publish job.
6. Для RC добавить tag trigger `*.*.*-rc-*` в development caller и negative filter для этих tags в production release workflow. Лёгкий prepare job проверяет RC через `build/source.py --rc-tag`: строгий формат с N>0, exact SHA и ancestry origin/builds; затем передаёт точное имя тега как release_version общим builders. Некорректные RC не доходят до platform jobs. Обычный builds/manual non-RC маршрут оставляет release_version пустым. RC retention равен builds; новые RC snapshots могут иметь cold tag cache.
7. Скилл мейнтейнера разделяет ручной intermediate режим и pre-release RC режим, фиксирует источник dev→builds, evidence/run/checksum и запрет перетегирования. Сначала проверяется выбранный snapshot, затем явно создаётся RC tag, после чего проверяются заново именно RC binaries. Создание 0.2.0-rc-1 сейчас не запрошено: финальный scope ждёт #121.

## Limits and official sources

- Репозиторий public, labels ubuntu-24.04/macos-15/windows-2022 являются standard runners. Их compute бесплатен для public repositories: https://docs.github.com/en/actions/how-tos/write-workflows/choose-where-workflows-run/choose-the-runner-for-a-job .
- Branch selection для dispatch поддерживается через --ref: https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow . Workflow уже существует в default main; новый push trigger не требует отдельного release tag.
- Native run 38460311002: Linux 16m58s, macOS 11m12s, Windows 17m07s, archive job 27s. Один run загрузил 477914038 bytes, включая 80033139 bytes итогового archive set.
- Default job limit 6 hours; проектный timeout 90m. Даже Free имеет 20 concurrent jobs/5 macOS, matrix использует 3/1: https://docs.github.com/en/actions/reference/limits . Фактическая загрузка остальных репозиториев аккаунта не проверена.
- Cache API: max_cache_size_gb=10, active size=11738972902 bytes на 14.10.2026. Превышение вызывает eviction; платное увеличение не выполнять: https://docs.github.com/en/actions/reference/workflows-and-actions/dependency-caching#usage-limits-and-eviction-policy . Cache из builds/ main доступен этой ветке; sibling dev cache не гарантирован, первый run может быть медленнее.
- Retention контролируется upload-artifact: https://docs.github.com/en/actions/how-tos/manage-workflow-runs/remove-workflow-artifacts . Account billing/budgets и тарификация накопленного storage не подтверждены; отсутствие лимита compute не означает отсутствие storage ограничений.

## Risks / Trade-offs

- [Сборка не соответствует ожидаемому dev snapshot] → сохранять run ID, builds SHA, metadata и checksum; читать actual ancestry после merge.
- [Первый builds run не видит sibling cache] → дать builders создать branch-local cache; не копировать caches и не увеличивать платный лимит.
- [Artifacts истекают] → скачать accepted evidence до expiration; для release использовать отдельный финальный run.
- [builds divergence] → обычный merge без force/reset; переносить CI change в dev через PR.

## Migration Plan

Создать builds без перезаписи существующей ветки, доставить bootstrap, проверить terminal matrix. Для прекращения автоматических сборок убрать push.branches=builds отдельным change; ручной и release-tag маршруты продолжают работать. Старые artifacts/cache не удаляются этим change.
