# Changelog

## [Unreleased]

### Исправлено

- Установщик Linux/macOS повторяет временные HTTP-ошибки, таймауты и отказы соединения до трёх раз с ограничением времени повторов. При окончательном сбое команды из README и руководства установки не подключают env и не запускают CLI. [#151](https://github.com/mesilov/bitrix24-cli/issues/151)

### Добавлено

- **Шаблоны задач:** `task:template:show`, `task:template:fields:list`, `task:template:add/update/delete` через REST 1.0 с нативными полями, JSON/plain и dry-run. Добавлен JTBD PM-08; неоднозначное подтверждение удаления отражается как partial (exit 3), доступность после запроса видна в JSON/human/plain. [#72](https://github.com/mesilov/bitrix24-cli/issues/72)

- **Проверка прав вебхука:** требования выбранной команды сверяются с разрешениями подключения; отдельный защищённый `state.json` хранит снимок на сутки. Смена credentials/манифеста обновляет проверку, нехватка scope блокирует только затронутую команду, а `connection:check` принудительно обновляет права и показывает диагностику в human/plain/JSON. При сбое сохраняется последний успешный снимок и применяется backoff 300 секунд. [#149](https://github.com/mesilov/bitrix24-cli/issues/149)

### Изменено

- Завершённый OpenSpec change правил мейнтейнера архивирован после merge MR #157 в `dev`, проверки changelog, успешного CI текущего head и снятия замечания review. [#156](https://github.com/mesilov/bitrix24-cli/issues/156)
- **Правила мейнтейнера:** каждый MR требует собственной содержательной записи changelog со ссылкой на issue. Через пять минут после открытия проверяются reviews, inline threads и обсуждение; перед merge все замечания должны быть сняты, активные запросы изменений отсутствовать, а обязательные GitHub CI checks — завершиться SUCCESS на текущем head SHA. [#156](https://github.com/mesilov/bitrix24-cli/issues/156)
- Требования сетевых повторов установщика и условных команд установки синхронизированы в `binary-distribution`; завершённый OpenSpec change архивирован после merge #155 и успешных post-merge проверок. [#151](https://github.com/mesilov/bitrix24-cli/issues/151)
- Требования проверки scope вебхука синхронизированы в актуальную спецификацию `connection-scope-validation`; завершённый OpenSpec change архивирован после доставки MR #150 в `dev` и устранения всех замечаний review. [#149](https://github.com/mesilov/bitrix24-cli/issues/149)
- Финальные receipts выпуска 0.2.0 и завершённый OpenSpec релиза архивированы после production/native/public-install проверки ([#109](https://github.com/mesilov/bitrix24-cli/issues/109)).

## [0.2.0] — 2026-10-14

В CLI теперь 56 публичных команд: 43 для задач, 4 для сотрудников, 5 для подключений, `doctor:problem-trajectory` и 3 общие команды.

### Добавлено

- **Жизненный цикл задачи:** `task:start`, `task:pause`, `task:defer`, `task:renew`, `task:complete`, `task:approve`, `task:disapprove`. Проверяются права действия и подтверждённый результат; частичное выполнение и неизвестный результат записи отражаются явно. [#60](https://github.com/mesilov/bitrix24-cli/issues/60)
- **Результаты задач:** `task:result:add`, `task:result:list`, `task:result:update`, `task:result:delete`. [#61](https://github.com/mesilov/bitrix24-cli/issues/61)
- **Мои задачи:** `task:my --user ID` с фильтром `--open`, границей `--due-before` и ограничением обхода `--max-scan`. ID исполнителя задаётся явно. [#118](https://github.com/mesilov/bitrix24-cli/issues/118)
- **Время задачи в статусах:** `task:status:durations` учитывает повторные открытия и границу `--as-of`. Суббота и воскресенье исключаются по серверному offset `created`; результаты каждого статуса представлены в `seconds`, `hours` и `days`. Часы и дни округляются независимо до двух знаков, день равен 24 часам. При недоказанной полноте возвращаются `complete=false`, неизвестные суммы `null` и exit code 3. Это календарное время, а не трудозатраты. [#121](https://github.com/mesilov/bitrix24-cli/issues/121), [#136](https://github.com/mesilov/bitrix24-cli/issues/136)
- **Сотрудники:** `users:current`, `users:find`, `users:colleagues`, `users:manager`. В профилях поддержаны `observerId`, `managerId`, `autoAddObserver`; наблюдатель может добавляться после создания задачи с сохранением подтверждённого ID при частичном выполнении. [#63](https://github.com/mesilov/bitrix24-cli/issues/63)
- **Фильтрация по роли:** `--role responsible|accomplice|creator|auditor` в `task:list` и `task:find`. Роль относится к пользователю подключения и сочетается с остальными критериями через AND. [#68](https://github.com/mesilov/bitrix24-cli/issues/68)
- **Календарные фильтры сроков:** `--deadline-window`, `--overdue`, `--timezone` в `task:list` и `task:find`; совместимы с фильтрами проекта, роли и названия. [#95](https://github.com/mesilov/bitrix24-cli/issues/95)
- **Теги:** повторяемый `--tag` в `task:add`/`task:update` и `--clear-tags` в `task:update`. Явный набор заменяет теги; отсутствие опции сохраняет их. [#71](https://github.com/mesilov/bitrix24-cli/issues/71)
- **Пользовательские поля:** `--user-fields`/`--user-fields-file` для создания и изменения задач; обязательные custom-поля отправляются при создании одним REST 1.0 вызовом. [#58](https://github.com/mesilov/bitrix24-cli/issues/58)
- **Локальные вложения:** `task:file:attach --file PATH --folder-id ID` загружает файл на Диск и прикрепляет к задаче. Поддержаны `--name`, потоковая передача, лимит по умолчанию 500 MB и сохранение Disk ID для восстановления после частичной привязки. [#62](https://github.com/mesilov/bitrix24-cli/issues/62)
- **Диагностика нехватки CLI:** `doctor:problem-trajectory` хранит обезличенные локальные карточки, показывает состояние и выполняет анализ снимков. Реальный endpoint отправки не включён. [#96](https://github.com/mesilov/bitrix24-cli/issues/96)
- **Общая справка и сведения о приложении:** `--help`/`-h` показывают назначение, полный каталог, глобальные опции и примеры задач; `--about` — версию, автора, лицензию и ссылки. Оба режима работают без подключения. [#100](https://github.com/mesilov/bitrix24-cli/issues/100)
- **Установка macOS/Linux:** POSIX installer проверяет SHA-256 и версию до замены бинарника, поддерживает свой каталог установки и `--no-modify-path`. [#28](https://github.com/mesilov/bitrix24-cli/issues/28)
- **Промежуточные сборки и RC:** ручной выбор снимков `dev` через ветку `builds`, теги `MAJOR.MINOR.PATCH-rc-N`, три native платформы и архивы с `SHA256SUMS`. RC не публикует Release и не меняет latest. [#109](https://github.com/mesilov/bitrix24-cli/issues/109)

### Изменено

- **Вся CLI-справка на русском:** заголовки, аргументы, опции, значения по умолчанию, примеры и ограничения. `completion`, `help`, `list` объединены в первый блок «Общие». Имена команд, флагов и API/JSON-полей сохранены. [#113](https://github.com/mesilov/bitrix24-cli/issues/113)
- **Читаемый вывод задач:** статусы, адаптация к ширине терминала, явные результаты полного и частичного обхода. Поиск совмещает название, ID, ответственного, статус, срок и JSON-фильтр через AND; JSON/TSV-контракты сохранены. [#12](https://github.com/mesilov/bitrix24-cli/issues/12)
- **README и руководства:** короткая входная страница, установка по платформам, подключение и конфигурация, 13 таблиц «Команда / Назначение / Пример». Детали вынесены в руководства установки, подключений, конфигурации, задач и сотрудников. Карта CLI актуализирована для 56 команд. [#134](https://github.com/mesilov/bitrix24-cli/issues/134)
- **Документация сценариев:** сохранены 22 JTBD с ролями, командами, статусами реализации, ссылками на тесты и границами покрытия; их актуализация обязательна при изменении связанных команд. [#80](https://github.com/mesilov/bitrix24-cli/issues/80), [#105](https://github.com/mesilov/bitrix24-cli/issues/105)
- **Сопровождение:** OpenSpec-трассировка и архивы завершённых изменений, обязательная запись CHANGELOG для PR по issue, ручные сборки и отдельная приёмка скачанных бинарников. [#65](https://github.com/mesilov/bitrix24-cli/issues/65), [#77](https://github.com/mesilov/bitrix24-cli/issues/77), [#78](https://github.com/mesilov/bitrix24-cli/issues/78), [#109](https://github.com/mesilov/bitrix24-cli/issues/109)

### Исправлено

- `task:list`/`task:find` обходят свежие задачи первыми (`id DESC`). В поиске доступен `--order`; неполный результат сообщает сортировку, объём обхода и необходимость увеличить `--max-scan`. [#64](https://github.com/mesilov/bitrix24-cli/issues/64)
- `connection:add` отклоняет существующий alias до ввода webhook и сообщает, что параметры не применены. Для изменения профиля документировано повторное создание. [#59](https://github.com/mesilov/bitrix24-cli/issues/59)
- Advanced-поля add/update проверяются по допустимости записи; `editable=false` не блокирует разрешённые поля создания. Обычный advanced dry-run работает без API. [#58](https://github.com/mesilov/bitrix24-cli/issues/58)
- Если `groupId=null` в REST 3.0 list, принадлежность проекту проверяется чтением карточки по заранее выбранному маршруту. Непроверенные данные дают partial result. [#60](https://github.com/mesilov/bitrix24-cli/issues/60)
- Обход истории `tasks.task.history.list` завершён при отсутствии `next`; `total` не обязателен. Уточнены границы текущей жизни задачи, повторные открытия и сохранение более ранней истории. [#121](https://github.com/mesilov/bitrix24-cli/issues/121)
- Устранены пять предупреждений strict-валидации OpenSpec без изменения CLI/API-контрактов. [#114](https://github.com/mesilov/bitrix24-cli/issues/114)

### Ограничения выпуска

- Полная portal-матрица прав, независимая проверка принадлежности вложений и полнота навигации учёта времени не подтверждены во всех случаях. #42 закрыто по решению пользователя после остановки проверок; это не означает полной приёмки. [#42](https://github.com/mesilov/bitrix24-cli/issues/42)
- По умолчанию CLI разрешает объявленные маршруты REST 3.0, REST 1.0 и IM. Только явно выбранный `--api-policy strict-rest3` запрещает non-v3 команды: `policy-denied`, exit code 4, без API-запросов.
- Архивы предназначены для Linux amd64, macOS arm64 и Windows amd64. macOS Developer ID/notarization и Windows Authenticode не заявляются. [#25](https://github.com/mesilov/bitrix24-cli/issues/25)
- Проверки RC, окончательной версии `0.2.0` и публикации Release фиксируются отдельно. Агентский скилл обновлён на все 56 команд: [PR #9](https://github.com/mesilov/bitrix24-cli-skill/pull/9) merged, [#6](https://github.com/mesilov/bitrix24-cli-skill/issues/6) закрыто.

### Предрелизная проверка

Кандидат `0.2.0-rc-2` (source `6931fd0440143878f6a19601bd87bbcfd51cbe70`) прошёл [сборку трёх платформ](https://github.com/mesilov/bitrix24-cli/actions/runs/38545765746) и [проверку скачанных бинарников](https://github.com/mesilov/bitrix24-cli/actions/runs/38548634484): 56 команд, 112 command-help вызовов, 125 CLI запусков и сверка 56 примеров на каждой ОС. Source CI: 825 тестов / 13 125 assertions. Это приёмка кандидата; опубликованная стабильная 0.2.0 получает собственные артефакты и проверку.

### Финальный выпуск

[Release 0.2.0](https://github.com/mesilov/bitrix24-cli/releases/tag/0.2.0) опубликован 14 октября 2026 и назначен latest. Source SHA: `6bba00c0599913a9f5cb0751bdf0d5dd7d7c489b`. [Production CI](https://github.com/mesilov/bitrix24-cli/actions/runs/38551708207): 6/6 jobs success. [Финальные бинарники](https://github.com/mesilov/bitrix24-cli/actions/runs/38554068826): 3/3 native платформы, 56 команд, 112 help-вызовов и 125 CLI запусков каждая. [Публичная установка Linux/macOS](https://github.com/mesilov/bitrix24-cli/actions/runs/38554073724) и все version/latest ссылки проверены.

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

[0.2.0]: https://github.com/mesilov/bitrix24-cli/releases/tag/0.2.0
