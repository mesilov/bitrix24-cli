# Proposal

## Why

Релиз 0.1.0 опубликован, но README.md всё ещё предлагает скачивать dev-кандидат через CI Artifacts. Пользователь запросил ссылки на бинарники и latest download links без обновления номера версии.

## What Changes

- Основным источником скачивания сделать публичный Latest Release.
- Добавить прямые ссылки latest/download на три существующих platform archives и SHA256SUMS.
- Сохранить dev CI artifacts как отдельную опцию и прежние инструкции установки/checksum, поддерживаемые платформы и macOS trust limits.

## Capabilities

### New Capabilities

Нет, `skip_specs: true`: только документация существующей публикации.

### Modified Capabilities

Нет. Прочитан binary-distribution/spec.md, включая Installation and evidence documentation и Release assets and publication gate; поведение/requirements не меняются.

## Impact

README.md и OpenSpec bookkeeping. Asset names, runtime, release workflow, published tag/assets и historical acceptance records неизменны.

## GitHub issues

- [#55](https://github.com/mesilov/bitrix24-cli/issues/55): актуальные release download links и README после публикации 0.1.0.
