# Design

## Context

См. proposal.md. Четыре workflow уже присутствуют в `dev`; default branch — `main`. Основной checkout содержит незакоммиченные изменения.

## Goals / Non-Goals

**Goals:** показать состояние push-проверок `dev` и дать прямой переход к тем же runs.

**Non-Goals:** менять workflows, линтеры или поведение CLI.

## Decisions

Использовать стандартные GitHub Actions SVG badges и Markdown под заголовком README. Явно задать `branch=dev&event=push`, поскольку бейджи без фильтра ориентируются на default branch, где workflows пока отсутствуют. В ссылках использовать `query=branch%3Adev+event%3Apush` для соответствия показываемому состоянию. Проверить четыре URL по действующим workflow-файлам и HTTP/SVG ответам.

## Risks / Trade-offs

Кеш GitHub может задерживать обновление бейджа; ссылка ведёт к исходным результатам Actions. Работать в отдельном worktree, чтобы сохранить текущие изменения основного checkout.
