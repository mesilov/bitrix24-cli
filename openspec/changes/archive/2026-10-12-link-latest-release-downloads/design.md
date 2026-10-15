# Design

## Context

См. proposal.md. Main `a49a188` и dev `5b738c0` имеют одинаковое дерево; публичный Latest Release — 0.1.0 с тремя archive assets и SHA256SUMS. Binary-distribution уже требует download/checksum documentation.

## Goals / Non-Goals

**Goals:** предоставить постоянные прямые download links, работающие после публикации следующих Latest releases с теми же asset names.

**Non-Goals:** изменять release workflow, installer/runtime, архивы/тег 0.1.0 или повторять portal acceptance.

## Decisions

- Использовать полные `https://github.com/mesilov/bitrix24-cli/releases/latest/download/<asset>` URL, описанные [GitHub Docs](https://docs.github.com/en/repositories/releasing-projects-on-github/linking-to-releases). Относительные Markdown paths ведут к файлам репозитория; версия-пиннированные URLs не выполняют запрос latest. Слово «относительные» трактуется как ссылки без фиксированного номера версии, что объяснено пользователю.
- Использовать существующие постоянные asset names. Latest Release page, три archive download links и SHA256SUMS выводятся в основном разделе Скачать; dev CI остаётся отдельной опцией с full SHA/auth/90-day retention.
- Сохранить весь раздел Установка и последующий README byte-for-byte, включая checksum из одного Release/CI run, native матрицу и macOS approval. Тег/публичные assets не менять.
- Доставить docs-only правку через task PR в dev и dev→main MR; включить актуальную main history в task branch merge-коммитом, сохранив baseline tree.

## Risks / Trade-offs

- Latest target меняется → документация требует архив и SHA256SUMS из одного Release; существующие инструкции checksum сохраняются.
- Несовпадение asset name даёт 404 → сверить API names и HTTP HEAD всех четырёх links, сохранить только безопасные GitHub redirect URLs без CDN signatures.
