# Changelog

## [Unreleased]

### Исправлено

- Выборка проекта при groupId=null в REST3 list: принадлежность проверяется через
  заранее объявленное REST3 чтение group.id карточки; непроверенные данные дают partial
  ([#60](https://github.com/mesilov/bitrix24-cli/issues/60)).

- Устранены пять strict-предупреждений OpenSpec о длине требований: составные
  нормы в четырёх спецификациях разделены с сохранением всех прежних сценариев
  и ограничений CLI/API; strict-валидация проходит без предупреждений
  ([#114](https://github.com/mesilov/bitrix24-cli/issues/114)).

- `task:list` и `task:find` обходят свежие задачи первыми (`id DESC`), сохраняя
  порядок в выводе. Поиск поддерживает `--order`; неполный обход показывает
  фактическую сортировку и число просмотренных задач, а при исчерпании бюджета
  предлагает увеличить `--max-scan` и предупреждает о неполной проверке дублей
  ([#64](https://github.com/mesilov/bitrix24-cli/issues/64)).

### Добавлено

- План расчёта календарного времени задачи в статусах: отдельные интервалы
  повторных открытий, суммы по статусам, граница `asOf` и явная неизвестность
  при неполной истории; реализация команды ещё не начата
  ([#121](https://github.com/mesilov/bitrix24-cli/issues/121)).
- Семь команд жизненного цикла задачи: start/pause/defer/renew/complete/approve/disapprove,
  проверка прав действия и обязательного native result, фактический REST3 статус,
  dry-run и сохранение confirmed/partial/outcomeUnknown. Строгая проверка шести
  логических статусов в фильтрах задач ([#60](https://github.com/mesilov/bitrix24-cli/issues/60)).

- Вся CLI-справка переведена на русский: общие и командные заголовки, аргументы,
  опции, значения по умолчанию, примеры и ограничения; встроенные `completion`,
  `help` и `list` собраны в едином первом блоке «Общие». Обновлены README и карта CLI,
  сохранены имена команд/флагов, схемы JSON/XML и shell-скрипты автодополнения
  ([#113](https://github.com/mesilov/bitrix24-cli/issues/113)).

- `doctor:problem-trajectory`: защищённые локальные карточки, root telemetry
  (сбор true/лимит 100), публичные status/list/configure и snapshot analysis,
  даты weekly предложения/обработки, recovery journal и явный offline feedback
  mock с идемпотентными тестовыми receipts. Реальный endpoint не включён
  ([#96](https://github.com/mesilov/bitrix24-cli/issues/96)).

- Общая справка `--help`/`-h` с назначением b24cli, полным списком публичных команд
  и примерами задач; `--about` с версией, автором, лицензией и ссылками на приложение
  и скилл. Обновлены командные примеры, README/JTBD и проверяемая карта CLI-вызовов
  ([#100](https://github.com/mesilov/bitrix24-cli/issues/100)).
- План сборки релиза 0.2.0 с повторной проверкой и актуализацией справки/about,
  документации и скилла на финальных артефактах
  ([#109](https://github.com/mesilov/bitrix24-cli/issues/109)).

- [JTBD задач по ролям](docs/task-jtbd.md): все 22 существующих сценариев в списках с ID,
  общими таблицами «Статус / Команда / Тест» для текущих и планируемых команд,
  статусами ✅ Готово / 🕓 План и навигацией по описаниям ролей,
  текущими и планируемыми CLI-командами, ссылками на файлы интеграционных тестов
  и границами покрытия ([#80](https://github.com/mesilov/bitrix24-cli/issues/80)).

- Карточки и списки задач с читаемыми статусами, адаптацией к ширине терминала
  и явными итогами полного/частичного обхода; практические примеры в help и
  безопасная диагностика ошибок ввода. `task:find` сочетает заголовок с ID,
  ответственным, статусом, сроком и JSON-фильтром через AND; контракты JSON/TSV
  сохранены ([#12](https://github.com/mesilov/bitrix24-cli/issues/12)).

- Календарные `--deadline-window`, `--overdue` и `--timezone` в `task:list`/`task:find`:
  серверные диапазоны дедлайна через REST 1.0, AND с проектом/ролью/названием,
  нормализованные lifecycle статусы и явный отказ strict-rest3 до запросов
  ([#95](https://github.com/mesilov/bitrix24-cli/issues/95)).

- POSIX installer для macOS arm64/Linux amd64: публичный Release, SHA-256,
  проверка версии перед заменой, notices, zsh/bash PATH, custom install directory
  и `--no-modify-path` ([#28](https://github.com/mesilov/bitrix24-cli/issues/28)).

- Фильтр `--role responsible|accomplice|creator|auditor` в `task:list` и
  `task:find`: свои задачи владельца подключения, сочетание с другими критериями
  через AND и явный маршрут REST 1.0 для соисполнителя и наблюдателя
  ([#68](https://github.com/mesilov/bitrix24-cli/issues/68)).
- Теги задач: повторяемый `--tag` в `task:add`/`task:update` и `--clear-tags`
  в `task:update`. Явный набор заменяет теги; отсутствие опции сохраняет их
  ([#71](https://github.com/mesilov/bitrix24-cli/issues/71)).
- Явные составные dry-run планы REST 3.0 и записи `TAGS` через REST 1.0,
  ранний отказ strict-rest3 и частичный результат с ID задачи для восстановления
  без повторного создания ([#71](https://github.com/mesilov/bitrix24-cli/issues/71)).
- `task:file:attach --file PATH --folder-id ID` загружает один локальный файл до 500 MB
  на Диск и прикрепляет к задаче; поддерживает имя через `--name`, безопасный dry-run
  и сохранение Disk ID для восстановления после частичной привязки. Runtime `maxUploadBytes`
  перекрывает default в обе стороны; большие файлы передаются потоково. Локальный ввод
  использует Symfony Filesystem/Path и Base64-кодировщик b24phpsdk
  ([#62](https://github.com/mesilov/bitrix24-cli/issues/62)).
- Namespace `users`: текущий пользователь подключения, поиск сотрудников по ФИО/email,
  коллеги текущего отдела и руководитель по оргструктуре либо из профиля.
  Добавлены профильные `observerId`, `managerId` и флаг `autoAddObserver`:
  при включении наблюдатель добавляется после создания задачи с сохранением
  существующих участников и подтверждённого ID при частичном выполнении
  ([#63](https://github.com/mesilov/bitrix24-cli/issues/63)).

### Изменено

- После merge [MR #117](https://github.com/mesilov/bitrix24-cli/pull/117)
  в `dev` и закрытия [#114](https://github.com/mesilov/bitrix24-cli/issues/114)
  завершённый OpenSpec change `normalize-openspec-requirement-statements`
  перенесён в архив `2026-10-14-normalize-openspec-requirement-statements`;
  все 16 синхронизированных delta-блоков и strict-валидация main specs сохранены.

- После merge [PR #116](https://github.com/mesilov/bitrix24-cli/pull/116) в `dev`
  и закрытия [#113](https://github.com/mesilov/bitrix24-cli/issues/113) требования
  русской CLI-справки и единого блока «Общие» синхронизированы в `cli-experience`;
  завершённый change `localize-cli-help-and-group-common-commands` перенесён
  в архив от 14.10.2026.

- OpenSpec MVP проблемных сценариев синхронизирован с актуальными
  `agent-trajectory-feedback` и `connection-configuration`; завершённый change
  перенесён в архив `2026-10-14-analyze-agent-trajectories` с evidence доставки CLI
  и скилла ([#96](https://github.com/mesilov/bitrix24-cli/issues/96)).

- После merge [PR #110](https://github.com/mesilov/bitrix24-cli/pull/110) в `dev`
  и закрытия [#100](https://github.com/mesilov/bitrix24-cli/issues/100) синхронизированы
  требования общей справки и `--about` в `cli-experience`; завершённый change
  `verify-cli-help-and-docs-0-2-0` перенесён в архив от 14.10.2026.

- Архивирован завершённый OpenSpec change `require-jtbd-documentation-sync` после
  доставки правила сопровождения JTBD и ссылки в шапке README через PR #106 в `dev`;
  сохранены CI/merge receipts ([#105](https://github.com/mesilov/bitrix24-cli/issues/105)).

- Закреплено обновление `docs/task-jtbd.md` в том же MR при реализации новых JTBD
  и изменении связанных команд: ID, роли, статусы, ссылки на файлы тестов и границы
  покрытия проверяются перед MR/merge. JTBD добавлены в верхнюю навигацию README
  ([#105](https://github.com/mesilov/bitrix24-cli/issues/105)).

- Архивирован завершённый OpenSpec change `document-role-jtbd-traceability` после merge
  документации 22 JTBD в `dev` и закрытия [#80](https://github.com/mesilov/bitrix24-cli/issues/80);
  сохранены результаты проверок и подтверждённая доставка PR #82.

- Синхронизированы требования читаемого вывода, диагностики и комбинированного
  поиска с сохранением актуальных фильтров дедлайнов; архивирован завершённый
  OpenSpec change `improve-task-cli-dx` после merge реализации в `dev`
  ([#12](https://github.com/mesilov/bitrix24-cli/issues/12)).

- Синхронизированы спецификации календарных фильтров дедлайна и архивирован
  завершённый OpenSpec change `add-task-deadline-windows` после merge CLI/skill
  и закрытия issue ([#95](https://github.com/mesilov/bitrix24-cli/issues/95)).

- Подтверждена публичная Unix установка `0.1.0` с installer из `main`;
  синхронизированы восемь требований binary-distribution и архивирован завершённый
  OpenSpec change с native CI/public delivery evidence
  ([#28](https://github.com/mesilov/bitrix24-cli/issues/28)).

- Синхронизированы спецификации загрузки локальных файлов (default 500 MB и приоритет
  runtime `maxUploadBytes`) и архивирован завершённый OpenSpec change
  `upload-local-task-files` с подтверждённой доставкой реализации в `dev`
  ([#62](https://github.com/mesilov/bitrix24-cli/issues/62)).

- Синхронизированы спецификации пользователей, рабочих ролей и автодобавки
  наблюдателя; `add-users-namespace` архивирован с сохранёнными результатами
  local/native/live проверок и ограничением проверки руководителя по оргструктуре
  ([#63](https://github.com/mesilov/bitrix24-cli/issues/63)).

- Синхронизированы спецификации фильтрации задач по своей роли и API policy;
  архивирован завершённый OpenSpec change `add-task-role-filters` с результатами
  проверок и delivery evidence после merge реализации в `dev`
  ([#68](https://github.com/mesilov/bitrix24-cli/issues/68)).
- Синхронизированы спецификации тегов задач и архивирован проверенный change
  `add-task-tags` после merge реализации в `dev`
  ([#71](https://github.com/mesilov/bitrix24-cli/issues/71)).
- `connection:add` отклоняет существующий alias до ввода webhook и явно сообщает,
  что параметры не применены; help и документация описывают полное повторное
  создание профиля. Завершённый OpenSpec change архивирован, требования
  синхронизированы с основной спецификацией
  ([#59](https://github.com/mesilov/bitrix24-cli/issues/59)).
- В скилле мейнтейнера требуется содержательная запись в changelog со ссылкой на issue
  для любого MR в `dev` по issue; проверка обязательна перед открытием MR и merge
  ([#78](https://github.com/mesilov/bitrix24-cli/issues/78)).
- Архивирован завершённый OpenSpec change `require-issue-changelog-before-dev-merge`;
  сохранены результаты проверки и архивная трассировка
  ([#78](https://github.com/mesilov/bitrix24-cli/issues/78)).

## [0.1.0] — 2026-10-12

Первая версия CLI для работы с задачами Битрикс24.

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

### Область сборки

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
  Developer ID/notarization пока не выполняются.

[Подробные границы приёмки и evidence](docs/rc-0.1.0.md).

[Unreleased]: https://github.com/mesilov/bitrix24-cli/commits/dev
[0.1.0]: https://github.com/mesilov/bitrix24-cli/milestone/1
