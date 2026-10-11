# Tasks

## 1. Installer entry point and release selection

- [ ] 1.1 Добавить POSIX install.sh, parsing `--version`, `--install-dir`, `--no-modify-path` и help, target/prerequisite detection; документировать prerequisites/options и проверить shell syntax, supported/unsupported target fixtures без изменения существующей установки.
- [ ] 1.2 Реализовать anonymous latest-stable/explicit-tag resolution и скачивание обоих assets одного tag; документировать выбор версии и проверить fixtures для stable, explicit prerelease, отсутствующего Release/asset и отказа от CI fallback.

## 2. Verified installation and updates

- [ ] 2.1 Реализовать checksum перед unpack, проверку layout/license files и candidate version; добавить fixtures для missing/malformed/duplicate/mismatched checksum, invalid archive и неправильной версии, подтверждающие отсутствие замены binary.
- [ ] 2.2 Реализовать mode 755, staged binary replacement, сохранение notices и cleanup; описать расположение файлов/обновление и проверить fresh install, repeat/update, default/custom paths с пробелами и сохранность прежних binary/config/data на всех предусмотренных отказах.

## 3. Shell integration

- [ ] 3.1 Создавать env-file с фактическим install directory и идемпотентный setup zsh/bash с ZDOTDIR; документировать source/new-terminal сценарии и проверить текущую, новую interactive/login shell с исходным PATH без install directory и повторное подключение без дубликатов.
- [ ] 3.2 Реализовать no-modify-path и проверяемый вывод activation instructions; документировать opt-out и подтвердить неизменность shell profiles, работоспособность custom directory и отсутствие `.` в PATH.

## 4. Native CI and documentation

- [ ] 4.1 Подключить installer acceptance к Unix native packages в CI через controlled release fixtures с recorded version identity; подтвердить успешные macOS arm64/Linux amd64 runs, fixtures и сохранение существующих packaging smoke/install checks.
- [ ] 4.2 Обновить README/build README: installer URL, public Release source, inspect-before-execution, manual installation #24 и launch/signing #23/#25; проверить локальные ссылки/anchors, документированные команды и точное обозначение публичной готовности.

## 5. Distribution readiness and verification

- [ ] 5.1 После доставки script в main и появления первого public Release выполнить anonymous download/install smoke с опубликованного URL и явно выбранного tag; записать URL/tag/version/checksum и результаты, отдельно от fixtures и CI. До этого не отмечать публичную доставку выполненной.
- [ ] 5.2 Сопоставить реализацию со всеми delta requirements/scenarios, выполнить strict OpenSpec validation и diff checks, подтвердить issue #28 ↔ change и milestone 0.2.0; записать verification с отдельными local/CI/public-distribution/signing результатами и ограничениями.

## Workflow follow-up

- Реализацию отправить PR в dev; main/tag/Release доставляются обычным release workflow.
- После завершения реализации и проверки синхронизировать delta specs, архивировать change и обновить путь в issue.
