# Verification: localize-cli-help-and-group-common-commands

Issue: [#113](https://github.com/mesilov/bitrix24-cli/issues/113). База: `dev` после merge плана [PR #115](https://github.com/mesilov/bitrix24-cli/pull/115), `1741b9c5104c47a211904a47185aa442229c12d6`. Дата проверки: 14.10.2026 (Asia/Bishkek).

## Scorecard

| Измерение | Результат |
| --- | --- |
| Completeness | 8/8 задач реализации и проверки; 2/2 ADDED требований реализованы |
| Correctness | 2/2 требований и 6/6 сценариев покрыты исходным кодом и проверками |
| Coherence | Решения design соблюдены; структура проекта, PSR12/PHPStan/Rector проверены |

## Требования и сценарии

| Сценарий | Реализация | Доказательство |
| --- | --- | --- |
| Root overview uses Russian prose | ApplicationInformation::help, B24Application::getDefaultInputDefinition, TaskHelpDescriptor | RussianHelpTest: русский root usage и опции; HelpAndAboutTest: одинаковый --help/-h, назначение/примеры |
| Every public command supports both Russian help forms | Переведённые metadata всех 47 команд; BuiltinHelpMetadata; TaskHelpCommand/Descriptor | RussianHelpTest: обе формы для всего реестра; HelpAndAboutTest/UsageDiagnosticsTest: область команды, повторное использование application, parser-valid примеры |
| Human metadata in supported help formats is localized | HelpDescriptors, RussianMarkdownDescriptor/JsonDescriptor/XmlDescriptor | RussianHelpTest: 47 × 3 non-text формата; JSON/XML сравниваются с native formatter, меняется только человеческое описание --no-ansi, схемы/ключи сохранены |
| Help localization preserves offline behavior and completion scripts | Без изменений API/credential handlers; native completion execution | RuntimeTest: 206 запусков без credentials с несуществующим env/config-dir, обе точки входа; сравнение 6 скриптов с native resources. RussianHelpTest: spy transport/resolver без вызовов; quiet/silent/ANSI/no-ansi |
| Standard commands are displayed together | ApplicationInformation: реестр → группы → сортировка; общий блок первым | RussianHelpTest: единственный «Общие», completion/help/list вместе; фактический root help просмотрен |
| Additional public and hidden commands are registered | Динамическая группировка и фильтр hidden | RussianHelpTest: дополнительные aaa/zzz, namespace и nested команды, скрытая команда; проверка точного состава, порядка и уникальности |

## Выполненные проверки

- `make unit`: **744 tests, 10868 assertions**, успешно. После последней правки пояснений с сохранением `now`/`route`/`partial` повторно прошли целевые help/about/usage тесты.
- `make lint-all`: allowed licenses, PhpCsFixer, PHPStan, Rector — успешно.
- `make cli-reference-check`: карта соответствует **47 публичным командам**; parser-проверка всех 47 опубликованных примеров входит в HelpAndAboutTest.
- `openspec validate localize-cli-help-and-group-common-commands --strict`: успешно.
- `git diff --check`: успешно.
- В одноразовом dev-контейнере установлены bash/zsh/fish и выполнены `bash -n`, `zsh -n`, `fish --no-execute` на фактически сгенерированных completion-скриптах: все три успешны.
- До/после сравнили offline-снимки определений всех 47 публичных команд: имена/порядок аргументов и опций, required/optional/array/negatable, shortcuts, defaults, наличие completion — совпадают. Дополнительно встроенные определения и значения completion сравниваются с исходными Symfony-командами в RussianHelpTest.

## Coherence и границы

Перевод сделан в метаданных и проектных descriptors, без изменений vendor и подмены строк в готовом терминальном выводе. Symfony 8.1.8 text/Markdown/JSON renderers адаптированы с сохранением MIT-атрибуции и `src/Console/SYMFONY-LICENSE.txt`; XML сохраняет native DOM и меняет только описание отрицательной опции. При обновлении Symfony регрессии схем/определений и разметки проверяются тестами.

README, docs/development и генерируемая CLI-карта описывают русский help и единый первый блок «Общие». Сценарии/ID/статусы и API-маршруты JTBD не менялись; docs/task-jtbd.md не требует правки. API/JSON-ключи, enum значения и исполняемый синтаксис сохранены. REST-контракты, интеграции и вызовы к порталу не изменялись; проверка официальной API-документации и portal acceptance в этот change не входят.

CRITICAL: 0. WARNING: 0. SUGGESTION: 0. Пропущенных применимых проверок нет; проверка change пройдена. GitHub CI и merge реализации подтверждаются отдельно после публикации PR. Архивирование следует после доставки реализации и закрытия #113.

Релизные артефакты 0.2.0 не собирались. Повторная проверка финальных сборок и при необходимости актуализация остаётся в [#109](https://github.com/mesilov/bitrix24-cli/issues/109).
