# Changelog

## [Unreleased]

## [0.1.0] — RC — 2026-10-12

Первая сборка CLI для работы с задачами Битрикс24. Кандидат собирается из `dev`;
бинарники CI сообщают версию `dev-<полный source SHA>`.

### Добавлено

- **30 команд задач:** создание, карточка, список и поиск по заголовку; изменение полей,
  ответственного и срока; удаление, схема полей и права пользователя.
- Чат задачи: чтение, отправка, изменение и удаление сообщений; прикрепление
  существующих файлов Диска по ID.
- Учёт времени: контекст, список, добавление, изменение и удаление записей.
- Чек-листы и вложенные пункты: создание, чтение, изменение, выполнение,
  возобновление и удаление. Управление участниками и чтение истории изменений.
- Именованные подключения: `connection:add/list/show/use/remove`, выбор через
  `--profile`, явный dotenv через `--env-file` и process webhook для автоматизации.
  Секреты хранятся отдельно от metadata, с Unix permissions или Windows ACL.
- Defaults создания задачи в профиле: `groupId`, `creatorId`, `responsibleId`,
  `allowsTimeTracking`; явно заданные поля имеют приоритет.
- Поле группы `--group-id`, фильтрация списка и поиска, ограниченный обход данных
  и признаки неполного результата. Advanced JSON и файловый ввод.
- Обычный вывод, JSON envelope и plain/TSV, справка и shell completion,
  режим без диалогов, `--dry-run`, подтверждение удаления и явные коды ошибок.
- Политики `task-v3`/`strict-rest3`, проверки привязки связанных объектов,
  timeout и прерывание; учёт подтверждённых шагов и неизвестного результата записи.
- Сжатые standalone-пакеты Linux amd64, macOS arm64 и Windows amd64 с PHP runtime,
  production-зависимостями, metadata, license notices и `SHA256SUMS`.
  Конечный macOS executable проходит strict проверку ad-hoc подписи.
- Изолированное Docker/Make окружение для Git worktree, unit и per-command
  integration suites, четыре CI-линтера и native packaging/acceptance matrix.
- [bitrix24-cli-license](LICENSE) на основе BSD-3-Clause, Copyright © 2026
  Maksim Mesilov <mesilov.maxim@gmail.com> и уведомление о владельцах товарных знаков.
  Полный текст поставляется в каждом архиве как `BITRIX24-CLI-LICENSE.txt`;
  исходные лицензии сторонних компонентов сохранены отдельно.

### Область RC

- Проверенные native системы: Debian 12 amd64, macOS 15 arm64 и Windows Server 2022 amd64.
  Архивы Linux arm64 и macOS Intel пока не предоставляются.
- Некоторые операции используют явно выбранные REST 1.0/IM маршруты;
  `strict-rest3` отклоняет их до обращения к API.
- Полная denied-role матрица, history/elapsedTime completeness, независимая проверка
  file membership, передача задачи второму пользователю и native REST3 auditors
  остаются в [#42](https://github.com/mesilov/bitrix24-cli/issues/42), milestone 0.2.0.
- Проверка CI через реальный внешний fork остаётся в
  [#41](https://github.com/mesilov/bitrix24-cli/issues/41), milestone 0.2.0.
- Lifecycle/result и остальные 29 post-MVP операций остаются вне этой сборки.
  Developer ID/notarization и публичная публикация Release ещё не выполнялись.

[Подробные границы приёмки и evidence](docs/rc-0.1.0.md).

[Unreleased]: https://github.com/mesilov/bitrix24-cli/commits/dev
[0.1.0]: https://github.com/mesilov/bitrix24-cli/milestone/1
