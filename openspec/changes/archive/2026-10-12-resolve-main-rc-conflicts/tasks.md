# Tasks

## 1. Согласование веток

- [x] 1.1 Сверить base/main/dev конфликтные blocks и документировать preservation обеих сторон; подтвердить main spec byte prefix и текущий macOS Gatekeeper/install текст README.
- [x] 1.2 Merge main в bugfix worktree и разрешить два конфликта; проверить zero unmerged paths/markers, неизменность README/spec относительно принятого dev и сохранность main ancestry после commit.

## 2. Проверки

- [x] 2.1 Подтвердить идентичность application/build/workflow/lock и прочих non-docs files исходному dev, strict OpenSpec, Markdown targets/anchors и diff check; сохранить verification с refs и фактическими результатами.

## Workflow follow-up

- Verify/archive change после локальных проверок; обновить archived path в #50.
- Открыть helper PR в dev, выполнить CI/review и поставить merge history.
- Проверить MR #49 MERGEABLE и main ancestor dev; обновить описание MR, закрыть #50 после поставки.
- Main MR не сливать в рамках этой задачи.
