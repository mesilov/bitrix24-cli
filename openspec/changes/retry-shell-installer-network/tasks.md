# Tasks

## 1. Ограниченные сетевые повторы

- [x] 1.1 Добавить retry-политику к latest и обоим assets; дополнить contract suite настоящим curl и локальным HTTP стендом для recovery 504/503/timeout/refused, исчерпания попыток и постоянного 404; проверить сохранность старой установки и частичного output.
- [x] 1.2 Описать retry count, backoff, retry-start budget, окончательную ошибку и явный тег в docs/install.md; сверить текст с curl options и записать исправление #151 в Unreleased CHANGELOG.

## 2. Условные команды установки

- [x] 2.1 Исправить Bash/zsh блоки README и shell-install примеры docs/install.md; исполняемыми проверками подтвердить остановку при download/install/activation failure и сохранение PATH после успеха, включая существующую установку.
- [x] 2.2 Добавить README.md и docs/install.md в paths workflow shell installer, чтобы проверки команд выполнялись при изменении примеров; проверить workflow и набор платформ.

## 3. Интеграционная проверка

- [x] 3.1 Выполнить sh -n, полный shell-installer contract suite, native archive acceptance, strict OpenSpec validation и git diff --check; оформить verification.md с покрытием всех требований и границами live/CI.

## Workflow follow-up

- Перед MR заново получить и интегрировать origin/dev; повторить затронутые проверки и проверить двустороннюю связь issue ↔ change, milestone, Bug/labels и CHANGELOG.
- Открыть MR в dev и проверить CI/замечания. После подтверждённого merge и закрытия #151 архивировать change, доставить архив в dev и удалить связанный worktree по правилам maintainer skill.
