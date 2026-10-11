# Verification

Дата: 2026-10-11. Issue: https://github.com/mesilov/bitrix24-cli/issues/24.

## Completeness, correctness, coherence

- 6/6 tracked tasks complete; README содержит первый запуск рядом со скачиванием и прямую ссылку на установку macOS/zsh.
- Delta specs: not applicable (`skip_specs: true`). Изменение уточняет существующее `binary-distribution → Installation and evidence documentation`; основной spec не меняется.
- Requirement mapping: `README.md → Первый запуск`; `build/README.md → Установка → macOS arm64 (zsh)` покрывает checksum, unpack, executable permissions, пользовательский bin, notices, текущий и постоянный PATH, проверку из другого каталога и новой сессии.
- Design adherence: пользовательская установка без sudo, отсутствие `.` в PATH, сохранение notices, `.zshrc` с ZDOTDIR и защитой от дубликатов, ссылка на отдельный macOS issue #23.
- Code pattern consistency: изменения реализации ограничены документацией; PHP, runtime, packaging, platform assets и существующие OS/trust требования сохранены.
- CRITICAL / WARNING / SUGGESTION: 0 / 0 / 0 по проверенному scope.

## Local execution

На macOS 15.5 (24F74), arm64 скачан настоящий `b24cli-release-assets` из
[CI run 38148884407](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407), указанного в README.
Checksum archive `b24cli-macos-arm64.tar.gz`:
`6c71a00b8ebd3746fd70ae8c9b5340844de089ff675b84a3f81133d83afaf6f0`.
Версия: `b24cli dev-a7024b1f0228f833d185c0e4d6c2f5a1e1a57a17`.

11 локальных проверок выполнены; подробные результаты в `installation-evidence.json`:

1. Документированные checksum и unpack команды успешно работают на архиве CI.
2. До установки в PATH голый `b24cli --version` возвращает 127 (`command not found`).
3. Одинаковый README/build snippet запускает `./b24cli --version` и `./b24cli --help` успешно.
4. Неверный checksum блокирует распаковку.
5. Отсутствующая строка checksum также блокирует распаковку.
6. `install -m 755` сохраняет binary bytes; все 10 внешних license files сохранены без изменений.
7. Текущая сессия после export/rehash находит установленный binary из `/tmp` и возвращает правильную версию.
8. Повторная настройка `.zshrc` в отдельном ZDOTDIR сохраняет ровно одну PATH строку.
9. Ветка fallback к пользовательскому `.zshrc` выбирает правильный путь.
10. Новая interactive login zsh находит тот же binary при исходном PATH без пользовательского bin.
11. Все 4 относительные ссылки README/build README, включая fragment anchors, разрешаются.

Команды извлечены из Markdown fenced blocks. Для защиты настоящего профиля только
ссылки `$HOME` в проверяемых snippets перенаправлены на `$B24CLI_TEST_USER_ROOT`:
временный пользовательский каталог с пробелами. HOME не переопределялся.
Использован отдельный ZDOTDIR, `TMPDIR` в разрешённом временном каталоге и минимальное
окружение без credentials. Новая shell стартовала с `/usr/bin:/bin:/usr/sbin:/sbin`;
её настройка из `.zshrc`, а не унаследованный пользовательский PATH, нашла binary.
Первый запуск harness без TMPDIR упёрся в ограничение временных файлов sandbox;
после задания writable TMPDIR оба локальных CLI вызова и весь сценарий прошли.

`openspec validate clarify-standalone-path-installation --strict` и `git diff --check` прошли.
Issue #24 подтверждено как open, milestone `0.1.0`, label `documentation`, с идентификатором
и путём change в body; proposal содержит полный URL issue.

## Evidence boundaries

Использован существующий CI binary; новую сборку runtime локально не запускали.
Для этого documentation change результаты его собственного PR CI фиксируются отдельно.
Новый Release не публиковался, merge не выполнялся, live portal не проверялся.
Gatekeeper/quarantine/signing/notarization не проверялись и не изменялись; issue #23 остаётся отдельным.
Настоящие пользовательские dotfiles и установка пользователя не менялись.

Все проверки в scope change прошли; изменение готово к архивированию и PR в dev.
