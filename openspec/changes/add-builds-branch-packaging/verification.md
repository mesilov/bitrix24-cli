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
