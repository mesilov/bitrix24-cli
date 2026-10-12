# Design

## Context

См. proposal.md. Main имеет четыре собственных commits: отдельную документационную поставку #35, promotion #34 и завершение macOS acceptance #44. Read-only merge-tree подтверждает только README/spec conflicts; приложение уже проверено на dev ed09688.

## Goals / Non-Goals

**Goals:** ancestry main → dev и чистый merge MR #49 с сохранением всех принятых документов.

**Non-Goals:** main merge/publication/tag, реализация будущих 0.2.0 changes, новый runtime/API контракт, изменение binary inputs.

## Decisions

1. Отдельная bugfix/50-resolve-main-rc-conflicts от актуального dev; основной и другие worktrees не меняются.
2. Перед разрешением сравнить base/main/dev и конкретные conflict blocks. Полный main binary-distribution является точным prefix dev; поэтому итоговая spec сохраняется byte-identical исходному dev, включая оба main macOS requirements и дополнительное сжатие. README сохраняет актуальную dev версию после проверки main Gatekeeper/install instructions; старые pinned artifact refs main заменены уже принятой RC навигацией.
3. Merge main без squash истории: после поставки origin/main должен быть ancestor dev. Code-level tree application/build/config/lock/workflows обязан остаться byte-identical исходному dev.
4. Тестировать документы и merge graph. Source input identity сравнить с dev; новые CLI tests для такого изменения не нужны. CI выполняется на helper PR и MR #49 независимо от исходных source-bound checks.
5. Change архивируется после локального verify. Поставка helper PR в dev и снятие GitHub CONFLICTING — workflow follow-up, подтверждаются по факту в issue/PR.

## Risks / Trade-offs

- Потерять main evidence → сравнить полный main spec prefix, macOS paragraph и архивные records; не выбирать conflict side без проверки содержания.
- Случайно изменить бинарные inputs → сравнить Git tree всех файлов вне docs/openspec до commit и после merge.
- Main/dev изменятся параллельно → обновить refs перед merge/delivery; повторить merge-tree для текущих head refs.

## Delivery

PR helper → dev, затем GitHub MR #49 MERGEABLE и main ancestry check. Main MR остаётся открытым. Обратный archived path в issue #50.
