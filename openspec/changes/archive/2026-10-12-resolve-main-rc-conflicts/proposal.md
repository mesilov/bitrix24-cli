# Proposal

## Why

MR #49 dev → main заблокирован конфликтами README.md и binary-distribution/spec.md: macOS first-launch изменения были поставлены отдельно в обе ветки, а dev дополнительно содержит RC документацию/профили/сжатие/лицензию. Пользователь запросил исправление конфликтов.

## What Changes

- Согласовать main 1fb709bd269ec815276f5e5ce75cca903f105cb4 с dev ed09688e5d44b4c76bd13c01b21748a789ad96cd в отдельной bugfix ветке/worktree.
- Разрешить два конфликта по содержанию обеих веток, сохранив текущий README первой сборки и все принятые main macOS инструкции/requirements.
- Проверить отсутствие изменений application/build/workflow/lock; выполнить strict OpenSpec/Markdown/graph/conflict-marker проверки, затем verify/archive.
- Поставить merge history через PR в dev, обновив существующий MR #49 до MERGEABLE.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

Нет. skip_specs: true: согласуются уже принятые документы и история, без новых требований/CLI/API поведения.

## Impact

История dev и OpenSpec bookkeeping. Предварительное сравнение показывает: полный main binary-distribution является точным byte prefix текущей dev specification; оба macOS requirements и все их scenarios сохраняются. Главные README additions main — newer artifact refs и уточнённый Gatekeeper текст; текущая RC версия dev содержит этот Gatekeeper текст и ведёт к актуальным успешным builds. Основной dirty main checkout сохраняется.

## GitHub issues

- [#50 — Разрешить конфликты RC 0.1.0 в MR #49](https://github.com/mesilov/bitrix24-cli/issues/50), milestone 0.1.0, labels bug/documentation: весь scope конфликта и обратная трассировка.
- [MR #49](https://github.com/mesilov/bitrix24-cli/pull/49): существующий promotion dev → main; в рамках этой задачи он не сливается.
