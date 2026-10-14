# Tasks

## 1. CI и документация

- [x] 1.1 Добавить push trigger только для builds, сохранив manual/release routes, SHA identity и отмену устаревших builds runs; проверить workflow syntax и event filter.
- [x] 1.2 Настроить retention 7/30 дней только для builds; проверить все четыре upload шага и отсутствие override у release/manual non-builds.
- [x] 1.3 Документировать обновление builds из dev, скачивание по run ID, checksum/version/source проверки и limits; добавить CHANGELOG #109, проверить команды и ссылки.

## 2. Доставка и проверка

- [x] 2.1 Выполнить целевые source/publish/compression checks, strict OpenSpec и diff check; сохранить verification с границами проверок.
- [ ] 2.2 Добавить обратную OpenSpec ссылку в #109 и открыть PR в dev после свежей интеграции origin/dev; проверить milestone, changelog и обе стороны трассировки.
- [ ] 2.3 Создать builds без force push с bootstrap CI change, подтвердить trigger и успешную native matrix на точном SHA; сохранить run/job/artifact receipts с retention.

## Workflow follow-up

- После review/merge реализации в dev синхронизировать binary-distribution и архивировать change; релизный issue #109 остаётся открытым до отдельного выпуска 0.2.0.
- Сборка из builds не закрывает приёмку #121/#60 или задачи prepare-release-0-2-0.
