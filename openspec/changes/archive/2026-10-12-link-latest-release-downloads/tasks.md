# Tasks

## 1. Скачать публичный релиз

- [x] 1.1 Заменить основной README download section на Latest Release, три direct archive links и SHA256SUMS; проверить существующие API asset names и HTTP HEAD 302→0.1.0→200 для всех четырёх URLs.
- [x] 1.2 Сохранить dev CI как отдельную опцию и весь раздел Установка/последующий текст byte-for-byte; проверить diff, текущие Markdown targets/anchors, отсутствие stale candidate wording в download section.

## 2. Совместная проверка

- [x] 2.1 Подтвердить docs-only diff, неизменность code/build/workflow/config/lock/tag/assets, strict OpenSpec, diff/conflict checks и сохранить verification с фактическими HTTP/Release refs.

## Workflow follow-up

- Verify/archive change и обновить archived path в issue #55.
- Task PR в dev, затем dev→main MR для README; релизный тег и бинарники не пересоздавать.
