# Verification: retry-shell-installer-network

Дата: 2026-10-15. Issue: https://github.com/mesilov/bitrix24-cli/issues/151. Milestone: `0.3.0`; существующие labels: `bug`, `documentation`. Ссылка на change и путь проверены в описании issue через GitHub API.

## Summary

| Dimension | Status |
| --- | --- |
| Completeness | 5/5 implementation tasks; 2 added requirements; 10 scenarios |
| Correctness | 2/2 requirements mapped to implementation; all 10 scenarios covered |
| Coherence | Native curl policy and documented Bash/zsh control flow match design; package validation and HTTPS restrictions retained |

Критических замечаний, предупреждений и предложений по локальной проверке нет. Реализация готова к MR. Архивирование следует после подтверждённого merge в dev по maintainer workflow.

## Проверки после интеграции origin/dev

- `sh -n install.sh` — passed.
- `python3 -m py_compile build/tests/shell-installer-check.py` — passed.
- `python3 build/tests/shell-installer-check.py --assets /private/tmp/issue-151-native-assets --version 0.2.0` — **12 tests, OK**, без skips. Среда: macOS arm64, Python 3.14, native curl 8.7.1, Bash и zsh.
- `openspec validate retry-shell-installer-network --strict` — passed.
- `openspec validate --all --strict` — **26 passed, 0 failed**.
- `git diff --check` — passed; changelog содержит содержательную запись #151 относительно целевой dev.

Native archive: публичный `b24cli-macos-arm64.tar.gz` релиза `0.2.0`, SHA-256 `7a1be396ead0d7c40bfcfde37b1e2ec0bb800a18ef08b991ba5ee8a7f8ea7380`. Проверены повторная установка, custom directory, опция no-modify-path, shell activation и запуск версии реального бинарника.

## Покрытие требований и сценариев

| Requirement / scenario | Implementation and evidence |
| --- | --- |
| Bounded installer network retries | `install.sh`: оба curl получают retry 3, connrefused и retry-max-time 360/900; исходные connect/max-time и HTTPS restrictions сохранены |
| Latest after 504 | `test_transient_http_recovery_with_real_curl`: реальный curl, 504 → 302 → 200, две попытки latest, успешная установка |
| Asset recovery | Тот же тест: archive 503/500 и SHA256SUMS 429/502 → 200; дополнительно latest 408 → success |
| Partial transfer timeout | `test_partial_download_timeout_with_real_curl`: сервер отдаёт первые 8 bytes и превышает полный per-attempt timeout latest после redirect и архива; оба production таймера уменьшаются в 60 раз, две попытки завершаются успешно, checksum и установка проходят |
| Refused connection | `test_refused_connection_with_real_curl`: незанятый loopback port, фактический curl error 7; сервер запускается после обнаружения отказа; retry и установка успешны |
| Retry exhaustion | `test_network_failures_preserve_install_with_real_curl`: HTTP 504 на каждом из latest/archive/SHA256SUMS, ровно четыре HTTP попытки и nonzero exit |
| Permanent failure / validation | Тот же тест: HTTP 404 на каждом endpoint, одна попытка; `test_failures_preserve_previous_install` дополнительно проверяет checksum, архив, licenses, execution/version и staging failures |
| Preserve existing installation | Для окончательных HTTP failures сравниваются binary, env, shell profiles, notices, config и user-data до и после; helper проверяет удаление временных/staging файлов |
| Conditional shell installation examples | `README.md`, `docs/install.md`: pipeline в subshell с pipefail; source env и version связаны через && |
| Download failure / installer failure / activation failure / success | `test_documented_shell_sequences`: пять фактических fenced blocks, Bash + zsh, **38 сценариев**; заранее существуют env и старый b24cli. Curl failure отдаёт успешный пустой script для проверки левой стороны pipeline. При сбое следующие шаги не вызываются; после успеха current-shell PATH указывает на активированный binary directory |

CI paths workflow `shell-installer.yml` включают README.md и docs/install.md в pull_request и push; native Linux/macOS матрица сохранена.

## Границы доказательств

Локальный HTTP transport adapter меняет только fixture URL, разрешённый протокол тестового endpoint и пропорциональное масштабирование обоих временных лимитов timeout-сценария. Повторами управляет настоящий curl; production install.sh не подменяется. Скрипты бинарников используются как offline fixtures, отдельно проверен опубликованный native macOS executable.

Это локальная приёмка изменённого source installer. Native Linux CI, проверки MR и отзывы ревьюеров фиксируются отдельно в GitHub. Публичный URL main остаётся на опубликованной версии до отдельной доставки в main; новый Release здесь не публикуется. Bitrix24 API не затрагивался и portal acceptance для этого изменения не требуется.

## Проверка замечания ревью

Замечание https://github.com/mesilov/bitrix24-cli/pull/155#discussion_r4285711502 подтверждено: budget 120 секунд не оставлял времени для retry после max-time 120/300 секунд. Budget увеличен до 360/900 секунд. Новый regression test на сохранённом прежнем install.sh дал два ожидаемых curl 28 failures (latest и archive), а на исправленном script оба сценария прошли. Масштабирование /60 применяется и к max-time, и к retry-max-time, поэтому исходное соотношение лимитов сохраняется.
