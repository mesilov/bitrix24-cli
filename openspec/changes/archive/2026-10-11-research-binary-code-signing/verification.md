# Verification: research-binary-code-signing

Дата: 11.10.2026. Issue [#25](https://github.com/mesilov/bitrix24-cli/issues/25), milestone `0.1.0`. Проверяется исследование; runtime CLI, сборка и CI не изменяются.

## Покрытие задач

| Задача | Свидетельство |
| --- | --- |
| 1.1 | research.md §1: commit `b53178e`, текущая OS/arch матрица, каналы; §2/6: границы #14/#23/#24 |
| 2.1 | §1/2: native arm64/ad-hoc, Gatekeeper, Developer ID, notarization, quarantine, Mach-O/PHAR и отдельная диагностика #23 |
| 2.2 | §2/6: enrollment, $99/год, waiver, страна/оплата, сертификаты/ключи, renewal и CI |
| 3.1 | §1/3: обычный EXE, OV/EV, SmartScreen, SAC, self-signed, MOTW, Store/MSIX/EXE и корпоративная политика |
| 3.2 | §4/6: Azure, SignPath, Certum, SSL.com, DigiCert; стоимость, доступность, hardware/cloud, timestamp и unknowns |
| 4.1 | §1/5: ELF, APT, RPM/DNF, IMA/IPE и отдельная подпись kernel modules |
| 4.2 | §5/6: GnuPG и Sigstore, checksum против удостоверенной подписи, trust/key lifecycle и расходы |
| 5.1 | §1/6: сравнительная матрица, dev/release стратегии, первый год и следующие 12 месяцев |
| 5.2 | Этот файл, первичные ссылки, price-observations.json, strict validation и связь proposal ↔ issue |

## Источники и цены

- Все ссылки на утверждения ведут на первичные источники; проверка выполнена 11.10.2026 через web-чтение/поисковое извлечение. Для динамических Apple documentation страниц использовалось доступное индексированное извлечение, а не выполнение их JavaScript. Источник execve — Linux man-pages; отсутствие общего paid-signing требования обозначено как вывод с ограничениями политики.
- 38 различных URL первичных источников в отчёте на момент проверки. Автоматическая проверка Markdown подтвердила HTTPS-схему, отсутствие пробельных ошибок и баланс code fences. Это проверка структуры ссылок; проверка соответствия утверждений содержанию источников выполнена отдельно при чтении.
- price-observations.json содержит три наблюдения публичных Certum product pages: дата, URL, SHA-256 полученного HTML, варианты срока/цены. В duration configuration Standard: €209/€319/€419 за 1/2/3 года; EV: €379/€599/€825. OSS: €49, срок не найден, out-of-stock установлен по странице. Raw HTML не включён в артефакты.
- Azure Basic: $9.99 × 12 = $119.88; общий Apple + Azure = $218.88. SSL.com cloud ориентир $129 + 12 × $15 = $309; вместе с Apple $408. Расчёты проверены локальным скриптом, налоги/renewal/checkout не подменены фиксированной ценой.
- Разрешены явные unknowns: регион заявителя/оплата, применимость OSS-программ, stock/checkout, сроки проверки, налоги, поддержка offline notarization текущего архива, совместимость signing с embedded PHP. Ни одно из них не представлено успешным экспериментом.
- При конфликте маркетинга EV и Microsoft выбран Microsoft. Для страны Azure выбрана специализированная quickstart; DigiCert monthly/yearly расхождение и неизвестный срок Certum OSS сохранены явно.

## Практические проверки и пределы

Практически выполнены: чтение baseline build/spec/release artifacts; GitHub API read issue #25 (OPEN, milestone 0.1.0) и PUBLIC visibility; извлечение публичной конфигурации цен Certum; проверка структуры Markdown, метаданных и бюджетной арифметики. Существующий #23 прочитан как историческая диагностика, не воспроизведён.

Не выполнены: enrollment/identity validation, оплата, выпуск сертификата, подписание b24cli, submission notarization, первый запуск браузерной загрузки на чистых ОС, SAC policy experiments и новая runtime acceptance. Эти действия не входят в research-only change; документальные выводы не заявлены практической гарантией запуска.

## OpenSpec и согласованность

Схема `spec-driven`; proposal/design/tasks существуют. `skip_specs: true` намеренно сохранён: исследование не вводит runtime requirements. Delta spec coverage, Requirement Implementation Mapping и Scenario Coverage — **Not applicable**. Дизайн соблюдён: сценарии/версии/каналы разделены, bypass scoped, стоимость условная, неизвестные расходы отмечены, результаты рядом с change. Формат согласован с существующим research-task-command-surface.

Code Pattern Consistency — **Not verified: runtime-код не изменялся**. Это ограничение отчёта, не утверждение о будущей реализации подписи.

`openspec validate research-binary-code-signing --strict` завершился с exit 0: change valid, нулевые delta specs допустимы при skip_specs. `openspec instructions apply --change research-binary-code-signing --json` подтвердил **9/9**, remaining 0, state `all_done`. Proposal содержит полный URL #25; актуальное описание issue содержит тот же change ID и активный путь перед архивированием, milestone сохранён.

| Измерение | Итог |
| --- | --- |
| Completeness | 9/9 задач; Spec Coverage — Not applicable (skip_specs) |
| Correctness | Документальные утверждения/цены/расчёты сверены; runtime Requirement Mapping и Scenario Coverage — Not applicable |
| Coherence | Design Adherence подтверждён; Code Pattern Consistency — Not verified (runtime-код не изменялся) |

CRITICAL: 0. WARNING для документальной области: 0. SUGGESTION: 0. В выполненных проверках критических проблем не найдено. Code Pattern Consistency не проверялся по указанной причине; успешность будущего runtime signing не заявляется.

`openspec archive research-binary-code-signing --yes --json` успешно создал `openspec/changes/archive/2026-10-11-research-binary-code-signing/`; `specsUpdated: false`. Все 9 задач завершены, delta specs для синхронизации отсутствуют. Отправка отчёта на review в dev и обновление remote issue body/path — следующие workflow операции, подтверждаемые результатами команд; не объявляются выполненными только по наличию локального файла.
