# Spec Delta

## Purpose

Обеспечить воспроизводимые проверки качества PHP-кода CLI и лицензий зависимостей локально и в GitHub Actions до принятия изменений.

## ADDED Requirements

### Requirement: Automatic quality checks
GitHub Actions MUST запускать отдельные проверки лицензий зависимостей, PHP-CS-Fixer, PHPStan и Rector для событий `push` и `pull_request` и отображать результат каждой проверки.

#### Scenario: Push to a development branch
- **WHEN** разработчик отправляет коммит в ветку репозитория
- **THEN** запускаются все четыре проверки с различимыми результатами

#### Scenario: Pull request from a fork
- **WHEN** открывается или обновляется pull request из fork
- **THEN** все четыре проверки запускаются без пользовательских секретов и credentials портала Bitrix24

### Requirement: Reproducible local commands
Репозиторий MUST предоставлять Make-команды `lint-allowed-licenses`, `lint-cs-fixer`, `lint-phpstan`, `lint-rector` и `lint-all`, использующие те же конфигурации и зафиксированные Composer-зависимости, что и CI, через Docker без требования локальных PHP/Composer.

#### Scenario: Fresh checkout
- **WHEN** зависимости установлены из `composer.lock` с dev-пакетами в подготовленном Docker-окружении
- **THEN** каждая команда доступна локально и в CI, а `lint-all` выполняет все четыре проверки

### Requirement: Failures remain visible
Каждая проверка MUST завершаться с ненулевым кодом при нарушении своих правил или ошибке подготовки/запуска и предоставлять диагностику в логе.

#### Scenario: Disallowed dependency license
- **WHEN** license checker обнаруживает зависимость с лицензией вне конфигурации разрешённых лицензий
- **THEN** соответствующая локальная команда и CI check завершаются ошибкой с указанием зависимости

#### Scenario: Code violation
- **WHEN** PHP-CS-Fixer, PHPStan или Rector обнаруживает нарушение своей конфигурации
- **THEN** соответствующая команда и CI check завершаются ошибкой с диагностикой

#### Scenario: Tool unavailable
- **WHEN** подготовка окружения или установка необходимого инструмента завершается ошибкой
- **THEN** соответствующий check завершается ошибкой, а не считается успешным

### Requirement: Checks do not rewrite source files
PHP-CS-Fixer и Rector MUST выполняться в режиме проверки без автоматического изменения проверяемых исходников.

#### Scenario: Proposed code changes
- **WHEN** инструмент предлагает исправить исходник
- **THEN** проверка сообщает о нарушении и исходник остаётся неизменным

### Requirement: CLI analysis scope
Проверки кода MUST покрывать PHP-исходники CLI в `src/` и точку входа `bin/console`, учитывать PHP 8.4 и анализировать только существующие пути проекта, исключая `vendor/` и служебные файлы.

#### Scenario: Current repository layout
- **WHEN** выполняются проверки в checkout CLI, не содержащем SDK-каталогов тестов и сервисов
- **THEN** проверяется код CLI без ошибок от отсутствующих SDK-путей; vendor-код не включается в область анализа исходников
