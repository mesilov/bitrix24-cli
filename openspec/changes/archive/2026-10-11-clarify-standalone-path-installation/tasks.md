# Tasks

## 1. Первый запуск

- [x] 1.1 Добавить README quick start рядом со скачиванием: checksum/unpack prerequisite, `./b24cli --version`, `./b24cli --help`, объяснение PATH и прямую ссылку на установку; проверить команды на macOS CI archive и локальные ссылки.

## 2. Установка macOS/zsh

- [x] 2.1 Написать полный checksum/unpack/install/permissions/license сценарий в build/README.md; проверить реальные archive contents, успешную установку и отказ от распаковки при неверном checksum.
- [x] 2.2 Добавить PATH текущей сессии и постоянную `.zshrc` настройку, проверки из другого каталога и новой zsh; выполнить примеры с изолированным пользовательским каталогом и проверить повторное добавление строки.
- [x] 2.3 Связать macOS first-launch issue #23; проверить URL и сохранить существующие platform/runtime/checksum/license требования.

## 3. Проверка изменения

- [x] 3.1 Выполнить strict OpenSpec validation, diff/link checks и сопоставление с требованием Installation and evidence documentation; записать результаты и пределы проверки в verification.md.
- [x] 3.2 Проверить issue #24 ↔ proposal, milestone 0.1.0 и label documentation в GitHub; подтвердить обе стороны связи до PR.

## Workflow follow-up

- Архивировать change после реализации и проверки; обновить путь в issue.
- Опубликовать ветку и открыть PR в dev с archive path и результатами проверки.
