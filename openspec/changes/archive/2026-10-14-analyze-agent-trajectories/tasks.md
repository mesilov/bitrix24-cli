# Tasks

Реализация согласованного MVP. Задачи отмечаются по результатам кода, проверок и согласованной правки скилла. Реальные чаты не собираются; отправка пока явно мокается по указанию пользователя.

## 1. Контракты команды и конфигурации

- [x] 1.1 Зафиксировать doctor:problem-trajectory actions/flags и bounded схемы report/settings/batch/result; проверить valid/invalid примеры, stdin/file exclusivity и documented JSON envelope; описать help/публичные возможности до изменения скилла.
- [x] 1.2 Добавить root telemetry в ProfileRepository/runtime и docs/config.schema.json с defaults true/100/null/null; проверить общие configuration fixtures: omitted object/поля, false, integer/timestamps/unknown keys и schema-runtime agreement; обновить docs/configuration.md, не изменяя REST fields и profile defaults.

## 2. Локальная регистрация и хранение

- [x] 2.1 Реализовать offline record через --report-file/stdin, server-assigned cardId/createdAt/state и registration idempotency; проверить privacy/size/schema errors без эхо входа, duplicate call и отсутствие portal/feedback requests; документировать безопасную карточку с алгоритмом словами.
- [x] 2.2 Реализовать .problem-trajectories по config resolver, atomic writes/защиту файлов и config initialization без credentials; проверить --config-dir/env/OS paths, spaces/Unicode, symlinks/path traversal, read-only storage и concurrent config/profile writes.
- [x] 2.3 Реализовать collect=false и file limit 100 с terminal-only cleanup; проверить 100 pending/storage-full, corrupt card, lowered limit и сохранность pending/analyzed/unknown; описать отключение и освобождение места без silent discard.

## 3. Публичный доступ и состояние разбора

- [x] 3.1 Реализовать status/list/configure actions, effective telemetry и analysisDue на UTC clock; проверить defaults без writes, настройку false/max, границу семи суток, null timestamps и обычные stdout/exit codes; описать интерфейс, позволяющий скиллу не читать служебные файлы.
- [x] 3.2 Реализовать record-offer/record-analysis по snapshot card IDs с atomic dates/states; проверить decline, failure, новые карточки во время разбора, повтор вызова и отсутствие потери concurrent updates; документировать независимость анализа и отправки.

## 4. Переносимый скилл

- [x] 4.1 Обновить bitrix24-cli-skill в его репозитории: version/help check → confirmed gap → sanitized report → CLI record → объяснение/immediate issue как опция; проверить сценарии normal multi-call/JSON parsing, missing command, collect=false/full и старого CLI без doctor; обновить command references без Bash/Python bypass.
- [x] 4.2 Добавить startup status и weekly предложение одного разбора с record-offer/record-analysis; проверить decline/suppression до семи суток, empty queue, отсутствие background job и минимальный пакетный UX на поддерживаемых agent shells.
- [x] 4.3 Добавить чтение/группировку карточек, сверку версии/дубликатов и подготовку кандидатов; проверить размеченные синтетические пакеты 1/2/3/20/50, one-report hypothesis и dedup-unchecked; документировать report counts без статистики всех запусков/пользователей.

## 5. Клиентский feedback handoff

- [x] 5.1 Реализовать публичное submit подтверждённого пакета через feedback port и явный --mock-feedback по уточнению пользователя; проверить отсутствие реальной сети, consent, одна group → один idempotency key и privacy checks; документировать тестовый receipt без утверждения публикации.
- [x] 5.2 Реализовать durable per-group receipt/state и reconcile submission-unknown; проверить partial batch, restart, lost response и отсутствие повторного обращения; хранить приватные status secrets отдельно и проверить отсутствие их в отчёте/логах/issue.
- [x] 5.3 Реализовать endpoint-unavailable fallback без прямых HTTP/SDK/MCP из скилла; проверить, что локальные record/analysis работают самостоятельно, pending submission не теряется и анализ не выдаётся за передачу; описать зависимость внешнего запуска от #32.

## 6. Сквозная приёмка MVP

- [x] 6.1 Прогнать offline end-to-end record → weekly offer → grouped analysis → consent → fake submit/reconcile на пакетах 1/2/3/20/50 и лимите 100; сохранить evidence для privacy, дат, счётчиков и числа пользовательских решений.
- [x] 6.2 Проверить публичный mock handoff/reconcile без сети и реального issue; подтвердить различие mock/real receipt, сохранность mock карточек при cleanup и endpoint-unavailable по умолчанию; записать live handoff #32 как последующий внешний этап.
- [x] 6.3 Выполнить openspec-verify-change, strict validation, проверки #96 ↔ proposal и CLI/skill/config contracts; подтвердить отсутствие требований/кода full-history collector или scheduler в MVP.
- [x] 6.4 Исправить подтверждённые review-регрессии CLI: домены вне списка TLD/IP, версии CLI и скилла в publication, privacy повреждённого reportedCliVersion, повтор superseded snapshot и лимит чтения допустимого publication; повторить targeted/full tests и quality/schema/CLI smoke checks.
- [x] 6.5 Исправить review пример скилла с постоянным registration key: явный уникальный opaque key для эпизода, сохранённый для retries; проверить skill structure, ссылки и два отдельных эпизода через публичный CLI.

## Workflow follow-up

- После фактической реализации/verify и поставки архивировать change, синхронизировать agent-trajectory-feedback/connection-configuration и обновить путь в #96.
- Сохранить архив через review в dev, затем удалить только worktree задачи по правилам maintainer.
- Сбор полных чатовых историй/JSONL/app-server/rollouts, независимые adapters, автономный scheduler и автоматическое исправление оформить отдельным change следующего этапа; эти работы не блокируют завершение MVP.
- По указанию пользователя отправка пока мокается. После запуска #32 согласовать HTTP-контракт и отдельно проверить live receipt/moderation/issue link; мок не является доказательством внешней доставки.
