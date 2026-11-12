# Spec Delta

## MODIFIED Requirements

### Requirement: Configuration schema structural coverage
The schema MUST describe version 1 objects, required metadata, profile aliases, safe credential references, API-named task defaults, local default.users role selectors with an opt-in boolean, root telemetry settings, root logging settings and strict types.

#### Scenario: Cover all version 1 configuration structures
- **WHEN** a version 1 configuration is validated with the published schema
- **THEN** profile metadata/aliases/credential references, API-named task defaults, role selectors with opt-in boolean, root telemetry and root logging are covered with strict types.

## ADDED Requirements

### Requirement: Root logging settings
Пользовательский config.json schema v1 SHALL принимать optional root object logging. Effective defaults MUST быть enabled=false, level="info", directory=null, fileMask="b24cli-{YYYY}{MM}{DD}.log" и retentionDays=7. Настройки SHALL относиться к config directory, не к profile или REST payload; чтение MUST NOT записывать defaults в файл.

#### Scenario: Omitted or partial logging
- **WHEN** logging отсутствует либо содержит только enabled=true
- **THEN** отсутствующие поля получают defaults без изменения файла, а явное значение сохраняется
- **AND** переключение portal profile не меняет logging этого config directory

### Requirement: Strict logging values
Валидный logging MUST содержать только пять полей: boolean enabled; level в нижнем регистре из debug, info, notice, warning, error, critical, alert, emergency; directory как непустую строку или null; fileMask; positive JSON integer retentionDays. JSON Schema MUST отклонять любое другое значение.

#### Scenario: Invalid logging input
- **WHEN** logging задан null, array или scalar, enabled не strict boolean, level вне списка или в другом регистре, directory пустой или не строка, retentionDays равен 0, строке, дроби или переполнению
- **THEN** JSON Schema и runtime-проверка LoggingSettings признают вход неверным; literal integer checks runtime отражены отдельно для 1.0/1e0
- **AND** unknown key внутри logging неверен; logging в profile, default или runtime --config не принимается за root user settings

#### Scenario: Invalid file mask
- **WHEN** fileMask пустой, длиннее 255 байт, содержит `/`, `\`, `..`, управляющие символы или `<>:"|?*`, либо не содержит каждый из токенов `{YYYY}`, `{MM}`, `{DD}` ровно один раз
- **THEN** JSON Schema и runtime-проверка признают маску неверной

### Requirement: Invalid logging does not break profile storage
Неверный root logging MUST NOT давать configuration-error при чтении и записи профилей и telemetry. При чтении CLI SHALL считать логирование выключенным; при записи MUST сохранять logging без изменений. Остальные root-ключи проверяются как раньше.

#### Scenario: Profile command with invalid logging
- **WHEN** config.json содержит logging с level="Info" и выполняется connection:use work
- **THEN** профиль work выбирается, logging в файле остаётся `{"level":"Info"}` без изменений

### Requirement: Logging persistence preserves configuration
Profile mutations и doctor telemetry updates MUST сохранять существующий root logging без изменений. Изменения profiles, defaultProfile, credential references и telemetry MUST NOT терять logging.

#### Scenario: Profile and telemetry writes keep logging
- **WHEN** config содержит logging и выполняются connection:add, connection:use, connection:remove или doctor configure
- **THEN** logging после записи совпадает с исходным

### Requirement: Effective logging in connection:show
connection:show SHALL показывать блок logging: эффективные значения пяти полей, абсолютный путь каталога и файла текущих суток и статус `ok`, `disabled` или `invalid` с причиной. Команда MUST оставаться локальной; вычисление блока MUST NOT создавать каталог логов. При enabled=true команда пишет свои записи жизненного цикла, как любая другая.

#### Scenario: Enabled logging shown
- **WHEN** logging.enabled=true и выполняется `connection:show --json`
- **THEN** JSON содержит logging с enabled=true, level, retentionDays, абсолютным путём к `b24cli-YYYYMMDD.log` текущих суток и статусом ok

#### Scenario: Invalid logging shown
- **WHEN** logging.retentionDays=0 и выполняется connection:show
- **THEN** вывод содержит статус invalid с причиной, а код выхода не меняется из-за logging

### Requirement: Shared logging schema and runtime cases
Общие fixtures MUST проверять согласие JSON Schema и runtime-проверки LoggingSettings для logging: omitted, partial, полный корректный объект, каждое неверное значение и unknown key. Документация MUST описывать поля, defaults, путь `logs/`, хранение 7 дней, ограничение данных SDK и несовместимость с прежними версиями CLI.

#### Scenario: Fixtures cover logging
- **WHEN** CI запускает schema check и ConfigSchemaContractTest
- **THEN** валидный и невалидный logging классифицируются одинаково обоими валидаторами, существующие fixtures сохраняются
