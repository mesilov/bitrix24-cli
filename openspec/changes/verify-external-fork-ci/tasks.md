# Tasks

Milestone: **0.2.0**, issue [#41](https://github.com/mesilov/bitrix24-cli/issues/41). Все проверки ниже ещё не выполнены; перенесены из внешней fork-части `add-ci-linters` task 2.2.

## 1. Подготовка реального fork case

- [ ] 1.1 Определить согласованный fork под другим владельцем и открыть тестовый PR в dev; записать owner/base/PR URL/head SHA и подтвердить, что source действительно принадлежит внешнему fork.
- [ ] 1.2 Проверить фактические owner approval и token permissions без пользовательских секретов/portal credentials; записать условия запуска и ограничения в verification.md.

## 2. CI evidence

- [ ] 2.1 Получить успешные реальные runs всех четырёх checks для fork PR на одном head SHA; сохранить workflow/job URLs, events и conclusions, не засчитывая skipped/cancelled/internal PR.
- [ ] 2.2 Закрыть собственный тестовый PR и очистить согласованные fixtures; записать cleanup, выполнить strict OpenSpec validation и verify, проверить issue ↔ proposal и сохранить границу RC 0.1.0.

## Workflow follow-up

- Архивировать только после фактической verification; обновить issue #41 на архивный путь.
