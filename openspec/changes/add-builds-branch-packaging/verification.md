# Verification: add-builds-branch-packaging

Дата: 14.10.2026. База: dev `5eb733f08aaa26b94b29f3fc65f1c8ba60a1adad`.

## Source checks

- actionlint 1.7.12 проверил все workflows: exit 0. ShellCheck/Pyflakes выключены; shell/application код не менялся. Официальный архив actionlint сверён по SHA256 `aba9ced2dee8d27fecca3dc7feb1a7f9a52caefa1eb46f3271ea66b6e0e6953f` из GitHub Release metadata.
- `python3 build/tests/source-check.py`: 12/12 tests OK, включая чистый tagless commit, dirty/mismatched inputs и main-tag guard.
- `python3 build/tests/publish-check.py`: 7/7 tests OK; fake transport, публикации не было.
- `python3 build/tests/compression-check.py`: 20/20 tests OK; fixtures, не native runtime acceptance.
- `openspec validate add-builds-branch-packaging --strict`: pass, без warnings.
- `git diff --check`: pass.
- Review YAML: push filter ровно builds, manual сохранён, source_sha=github.sha, release_version не передан, concurrency per workflow/ref с отменой старого run. Caller имеет contents:read и только packages job. Production release.yml не изменён.
- Retention: три platform upload шага получают 7 для refs/heads/builds, archive set — 30; non-builds получает 0 (repository default). Типы событий PR/dev/production-tag не добавлены в caller push filter.

## Completeness / Correctness / Coherence

| Dimension | Result |
| --- | --- |
| Completeness | Source implementation готова; external delivery и native run ожидаются |
| Correctness | 2 delta requirements mapped: CI artifact verification → caller; Intermediate artifact retention → четыре upload шага и build README |
| Coherence | Existing builders, SHA identity, manual route и main-tag production guard сохранены; bootstrap branch ownership описан в design |

Сценарии negative triggers/manual/production retention проверены source review и actionlint; новых live runs для этих ref не запускали. Live builds-trigger/retention будет подтверждён отдельным run. Application PHP/unit/linters на новом SHA будут проверены GitHub source CI; локально application suite не повторяли, поскольку PHP/application/dependency inputs не менялись. Полная 0.2.0 приёмка, #121/#60, browser quarantine и публичная установка не выполнялись этим change.

## Delivery and native evidence

Ожидаются PR в dev, bootstrap builds SHA, push-triggered run и terminal job/artifact receipts. Issue #109 остаётся открытым, release не публикуется; change не архивируется до review/merge в dev.

## Bootstrap delivery

- Fresh dev перед PR: `357cb7a`, получена fast-forward после первоначального планирования. От `5eb733f` изменились только OpenSpec документы; application/build/workflows/tests/lock не изменились.
- PR https://github.com/mesilov/bitrix24-cli/pull/132 — open, target dev; #109 milestone 0.2.0 и labels enhancement/documentation проверены, обратная ссылка add-builds-branch-packaging добавлена в body issue.
- Remote builds создана на `30fa7053a2b031b4c80ac2aa3b503027a2a3e4b9`; source CI 13 checks SUCCESS на этом SHA.
- Ранние feature-push PHPStan/Rector attempts упали до линтеров с HTTP 500 Docker Hub на manifest php:8.4-cli-alpine3.23. Logs: runs 38532027440/38532027437. Повтор обоих failed jobs успешен; сохранён исходный факт сбоя.
- Push-triggered native run: https://github.com/mesilov/bitrix24-cli/actions/runs/38532046240 , event=push, branch=builds, source SHA=`30fa7053a2b031b4c80ac2aa3b503027a2a3e4b9`. Terminal outcome ожидается; не выдавать source CI за native proof.

## RC and maintainer-skill follow-up

- По дополнительному запросу пользователя скилл описывает два ручных режима builds, numbered RC identity, evidence и запрет перетегирования. quick_validate.py: Skill is valid; 13 ссылок проверены, отсутствующих локальных файлов нет.
- source-check теперь 14/14 tests: light/annotated RC tags в builds history, exact SHA mismatch, invalid/zero/leading-zero counters и unselected snapshot rejection. Production source guard отдельно отклоняет RC.
- actionlint всех workflows и strict OpenSpec проходят; 5 локальных tag-route cases показывают RC только в development, stable только в production, invalid RC в development validation gate. Prepare job перед matrix, contents:read, publish отсутствует.
- RC версия передаётся существующим builders через release_version, который поддерживает prerelease string; RC retention 7/30. Live RC tag/build не создавался: нет выбранного финального кандидата и #121 остаётся в scope.

## Terminal native bootstrap and current snapshot

- Первый run 38532046240 completed/success на `30fa7053a2b031b4c80ac2aa3b503027a2a3e4b9`: 4/4 jobs SUCCESS (Linux amd64, macOS arm64, Windows amd64 и verified archive set). Raw sanitized receipts: [native-bootstrap-receipt.json](native-bootstrap-receipt.json).
- Все три platform artifacts истекают 21.10.2026 (7 дней), b24cli-release-assets — 13.11.2026 (30 дней). Archive set: 80066904 bytes. Независимый BuildKit record имеет default 90 дней и занимает 109906 bytes; platform/archive retention не распространяется на этот служебный record.
- Native jobs проверили copied artifacts, version, command help, profiles, compression, linkage, checksums/archive/install contracts вне checkout. Это CI proof bootstrap snapshot с 42 task-командами, а не финального RC/current snapshot.
- Дополнительная локальная загрузка macOS artifact была прервана transport TLS handshake timeout; локальная проверка скачанного бинарника не засчитывается.
- #121 закрыто и доставлено в dev `6efba52`; source/test/docs changes интегрированы обычным merge, CHANGELOG сохраняет обе стороны. Обнаружен stale packaging inventory 42 → исправлен на 43 с registration/ID validation task:status:durations. README соответствует 43 task-командам.
- RC/skill/current-dev SHA `9c268e5bbb6b3c3cea85cfbb3726cbd8b5efbb99`: 13 source CI checks SUCCESS. Builds fast-forward на этот SHA после terminal результата первого run. Следующий run: https://github.com/mesilov/bitrix24-cli/actions/runs/38534772781 ; его native terminal outcome проверяется отдельно, не переносится из первого run.
- Change: 9/9 задач реализации/исходной native acceptance выполнены; review/delivery в dev и archive остаются follow-up. Live RC-tag acceptance не выполнялась, numbered candidate не создан. Финальная 0.2.0 приёмка и публикация не выполнены.
