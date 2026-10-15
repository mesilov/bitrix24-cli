# Design

## Context

См. proposal.md — Why. Прочитаны README, актуальный build guide, binary-distribution целиком, release-assets.py и install-check.py. Архивы содержат executable в корне, metadata и license notices. В dev уже включены инструкции macOS/zsh из #24; они сохранены при синхронизации ветки PR #29. #23 остаётся OPEN.

## Goals / Non-Goals

**Goals:** Дать последовательные команды для скачанного пакета и пользовательского PATH без sudo; скрыть объём текста в трёх раскрывающихся блоках; явно ограничить утверждения о запуске.

**Non-Goals:** Менять поддержку ОС, repair/sign бинарник, внедрять shell installer, глобально отключать защиту, выполнять подключения к порталу или выдавать документацию за native acceptance.

## Decisions

1. GitHub `<details><summary>` без `open`, с пустыми строками вокруг Markdown внутри. Отдельный блок на платформу; общий download/architecture prelude остаётся видимым. Это прямое соответствие просьбе пользователя, сохраняющее компактный README.
2. Рекомендовать общий artifact `b24cli-release-assets`, раскрыть внешнюю ZIP-обёртку CI. Проверять конкретный archive по SHA256SUMS из того же run; checksum failure останавливает распаковку (`&&` на Unix, throw на PowerShell). Не смешивать platform debug artifacts с release archives.
3. Linux/macOS: `$HOME/.local/bin` и notices/metadata в `$HOME/.local/share/b24cli`; текущий PATH и сохраняемая настройка Bash/zsh. Windows: весь пакет в пользовательский LocalAppData/Programs/b24cli, пользовательский PATH с сохранением остальных entries. Конфигурации shell не перезаписываются; путь с пробелами заключён в кавычки.
4. Первый запуск проверяется до PATH; command-not-found отделён от macOS отказа ОС. Для macOS описать штатное Open Anyway с ограничением #23; для Windows — точечный Unblock и SmartScreen, без обещания обхода SAC/корпоративных запретов. Источники Apple/Microsoft рядом с соответствующими пояснениями.
5. README — основной источник пользовательских команд. Build guide с прежними платформенными заголовками ссылается на него; runtime requirements и HTTPS trust остаются в build guide. Дополнительный контент CLI, включая --group-id migration, сохраняется.

## Risks / Trade-offs

- [Инструкция воспринимается как исправление #23] → указать известный exit 137/strict signature failure и остановку при нём; штатное разрешение не гарантировать для повреждённого файла.
- [Нет native Windows для исполнения новых команд] → проверить структуру/документацию PowerShell и явно обозначить отсутствие native запуска; существующий packaging smoke не выдать за проверку README.
- [Markdown внутри details отображается текстом] → проверить GFM-render через GitHub Markdown API, три summary и закрытое состояние блоков.
- [Checksum/распаковка/установка расходятся с archive layout] → сверить packager и выполнить безопасные fixture-проверки checksum/PATH/notice copy; fixture не является runtime acceptance.
