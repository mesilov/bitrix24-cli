# Design

## Context

См. proposal.md. На начало работы main `045e7ab` и dev `2760a7e` имеют идентичные деревья; полный native matrix dev и все шесть main checks успешны. Release workflow запускается push версии и публикует проверенные архивы отдельно от этой документационной правки.

## Goals / Non-Goals

**Goals:** согласовать финальный changelog и его текущие ссылки с датой и фактической областью первой версии.

**Non-Goals:** менять runtime, публиковать Release/тег, переписывать исторические OpenSpec evidence или расширять acceptance.

## Decisions

- Удалить RC из заголовка changelog, описание dev-кандидата из введения и предрелизную фразу о публичной публикации; сохранить все feature bullets, дату и ограничения #41/#42/платформ/Developer ID. Переписывание всего changelog не требуется.
- Исправить оба текущих Markdown anchor references; убрать RC из видимой подписи README на этот заголовок. docs/rc-0.1.0.md остаётся историческим acceptance record, его название и scope не меняются. Сохранение старого anchor как псевдонима вводило бы ненужный legacy элемент в первую версию.
- Доставка через task PR в dev и отдельный dev→main MR. Если нужна история последнего main merge, перенести её настоящим merge в task branch без изменения baseline tree; прямой push main не использовать.

## Risks / Trade-offs

- Изменение заголовка ломает anchors → проверить обе текущие ссылки и Markdown targets после правки и архива.
- Финальный заголовок могут принять за новое acceptance → сохранить фактические ограничения и не заявлять release/tag/portal/fork как выполненные.
