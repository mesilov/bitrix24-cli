# Tasks

## 1. Scope и документация

- [x] 1.1 Проверить milestone 0.2.0, issues/PR и согласованный scope; сохранить перечень готовых и оставшихся задач в release report.
- [x] 1.2 Перепроверить и при необходимости актуализировать результаты #100 на финальных исходниках: root help, командный help с примерами, about, карту CLI и README/JTBD ссылки; сохранить source SHA и результаты сверки.
- [x] 1.3 Передать итоговые каталог и version/source SHA в issue скилла #6; проверить ссылки и согласованность материалов скилла с финальным CLI.
- [x] 1.4 Актуализировать CHANGELOG/release notes, ограничения и OpenSpec-трассировку; проверить рабочие ссылки и соответствие реальному scope.

## 2. Сборка и приёмка 0.2.0

- [x] 2.1 Выполнить обязательные тесты/линтеры и сохранить успешный terminal CI для точного source SHA.
- [x] 2.2 Собрать Linux amd64/macOS arm64/Windows amd64 архивы и SHA256SUMS; проверить checksum, packaging metadata и идентичность версии/source SHA.
- [x] 2.3 На каждом финальном артефакте 0.2.0 повторить общую/командную help-проверку и about из #100 вне checkout, без credentials; сверить версию с --version, фактический каталог, документы и примеры, при расхождениях актуализировать и повторить проверку.
- [x] 2.4 Выполнить существующую native acceptance матрицу установки и запуска; сохранить результаты по каждой платформе и явные оставшиеся ограничения.

## 3. RC 0.2.0-rc-1 без публикации

- [x] 3.1 Дождаться зелёных CI/builds, merge #132 в dev с head guard, выбрать проверенный SHA из builds и создать 0.2.0-rc-1 без перемещения тега; проверить PR/tag/ancestry readbacks.
- [x] 3.2 Дождаться RC native matrix и archive job, скачать immutable artifact set и сохранить run/source/tag/checksum receipts; проверить отсутствие RC Release и неизменный latest.
- [x] 3.3 Реализовать helper готовых artifacts и проверить RC бинарники на трёх native runners без пересборки: identity/56 commands/help/about/offline JSON/errors, сохранить terminal results.
- [x] 3.4 Сохранить RC report, ограничения, OpenSpec-трассировку и CHANGELOG в PR dev, отдельно от stable-release acceptance; проверить отчёт/links/diff.

## 4. RC 0.2.0-rc-2 и подготовка финального выпуска

- [x] 4.1 Доставить служебные PR и согласовать финальный dev scope с README/скилл чатами; зафиксировать точный выбранный SHA и remaining scope.
- [x] 4.2 Усилить offline artifact проверку about/purpose/links, публичного каталога и документированных примеров; проверить source help/docs/reference и синтаксис примеров без портала.
- [x] 4.3 Дождаться terminal source CI, выбрать dev snapshot, перенести в builds и дождаться зелёной intermediate native matrix.
- [x] 4.4 Создать отсутствующий immutable tag 0.2.0-rc-2 на принятом builds SHA; дождаться зелёной RC matrix/archive и проверить tag/source identity.
- [x] 4.5 Проверить готовые RC2 архивы на трёх native OS вне checkout: checksum/version/about/help/catalog/offline/errors; скачать receipts и явно сохранить границы установки/portal.
- [x] 4.6 Завершить синхронизацию скилла #6 через PR; передать exact RC2 version/source/catalog и проверить delivery readback.
- [x] 4.7 Сохранить RC2 report, release notes, CHANGELOG, issue trace и native/CI receipts в dev через PR; проверить отсутствие RC Release/latest unchanged, оставить stable tasks pending.

- [x] 4.8 Открыть MR dev → main по запросу пользователя, разрешить конфликты истории main через PR в dev, дождаться CI и проверить mergeability/точный head без merge/publish.

## 5. Разрешённая публикация 0.2.0

- [x] 5.1 Проверить exact-head CI/review MR #143 и подтвердить merge в main с SHA/tag ancestry.
- [x] 5.2 Дождаться production native/archive/publication jobs; проверить stable Release, assets, metadata/tag identity и exact-source CI.
- [x] 5.3 Повторить downloaded-artifact acceptance на трёх native OS и проверить public/latest URLs, Unix installation и source/version.
- [x] 5.4 Подготовить final notes/CHANGELOG/receipts, проверить release change и сформировать архив с актуальной OpenSpec-трассировкой.

## Workflow follow-up

- Promotion в main, создание тега и публикация Release выполняются после согласования конкретного release scope.
- После разрешённой публикации проверить assets и latest-ссылки, закрыть issue по receipts и архивировать change.

## Post-delivery maintainer readbacks

Merge архивного PR в dev, обновление/закрытие #109 и удаление собственных worktree выполняются после Git delivery. Эти административные readbacks отслеживаются отдельно в #109; они не объявлены выполненными этим archive snapshot и не являются самоподтверждающей задачей артефакта.
