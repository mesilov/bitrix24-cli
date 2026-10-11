# Tasks

Постановка #15 и серверной подзадачи [#32](https://github.com/mesilov/bitrix24-cli/issues/32) подготовлена для milestone `0.2.0`; ниже задачи реализации, все остаются открытыми. См. `specs/user-feedback/spec.md` и `design.md`.

Серверная область #32: 1.1–1.2, 3.1–3.4, 4.1–4.3, 5.1–5.2. CLI/документация #15: 2.1–2.3, 5.3–5.4. Сквозная приёмка 6.1–6.2 выполняется совместно по обоим issues. Планирующий PR #16 идёт в `dev`, затем отдельный PR `dev → main` переносит план в релизную ветку; эти слияния не закрывают implementation tasks.

## 1. Подготовка внешнего сервиса

- [ ] 1.1 Получить URL основной feedback-формы от владельца (он сообщит позже), зафиксировать владельца triage, support email, hosting/storage/email adapters и срок хранения; после получения адреса проверить доступность каналов без GitHub-аккаунта и сохранение receipt/publication/status контракта. Не подменять URL выдуманной рабочей ссылкой.
- [ ] 1.2 Подготовить deployable `feedback-service/`, durable receipt/contact storage и outbox с раздельной конфигурацией секретов; проверить транзакционную запись, restart/recovery и отсутствие secrets в репозитории/логах, документировать запуск.

## 2. Локальный CLI и диагностический отчёт

- [ ] 2.1 Добавить независимую команду `feedback`, allowlist report и URL/file output; проверить Docker/headless запуск без portal credentials, отсутствие portal/feedback HTTP calls и общего GitHub token в CLI; при отсутствующем URL проверить сохранение отчёта, понятное сообщение и отсутствие открытия placeholder ссылки, обновить help.
- [ ] 2.2 Добавить явный `--open` и fallback при недоступном браузере; проверить, что отчёт/URL сохраняются в этом случае и default запуск не открывает браузер, документировать передачу отчёта через форму.
- [ ] 2.3 Проверить исключение raw argv/env/webhook/token/REST payload/task data и возможность просмотра/редактирования перед передачей; добавить проверки с синтетическими секретами и документировать поля отчёта.

## 3. Форма, receipt и moderation

- [ ] 3.1 Реализовать форму без аккаунта: описание/ожидания/факт/шаги, optional report/email и publication consent; проверить отправку без GitHub-login/контакта, отказ от публикации и idempotent повтор submission, документировать consent.
- [ ] 3.2 Реализовать validation, size/rate limits, антиспам и fixed target repo; проверить oversized/abusive запросы и запрет клиенту управлять GitHub target/credentials, документировать лимиты и ошибки.
- [ ] 3.3 Реализовать приватный status/access token, уточнения и contact deletion с защитой log/referrer leakage; проверить недоступность чужого receipt, раздельное хранение контакта и прекращение уведомлений после удаления, документировать сохранение/отзыв ссылки.
- [ ] 3.4 Реализовать authenticated moderation/triage: approve/edit/reject/duplicate и привязку канонического issue; проверить publication body без private contact, объяснение решения на status page и checklist issue↔OpenSpec/milestone/category, документировать операционный порядок.

## 4. GitHub App и публикация

- [ ] 4.1 Реализовать App installation authentication с `Issues: write` только для целевого repo и серверным хранением ключей; проверить provider adapter и истечение/обновление token, документировать установку и отключение App.
- [ ] 4.2 Реализовать publication worker, durable claim, receipt marker и обработку `publication-unknown`; проверить timeout после создания issue, concurrent/repeated обработку и GitHub outage без слепого повторного POST, документировать recovery.
- [ ] 4.3 Реализовать webhook ingress с signature/repo/event/action checks и delivery deduplication; проверить invalid signature, чужой repo, repeated delivery и недоступность downstream worker, документировать redelivery/reconciliation.

## 5. Ответ пользователю и интеграция CLI

- [ ] 5.1 Реализовать статусы triage/уточнений/решения, moderated clarification response и отдельное подтверждение release/version; проверить полный цикл без email/GitHub-login, duplicate/rejection и закрытие issue до релиза, документировать значения статусов.
- [ ] 5.2 Реализовать optional verified email, confirmation/resolution/release outbox notifications; проверить сбои почты, повтор обработки без дубля доставленного уведомления и отмену queued уведомлений при contact deletion, документировать opt-in/unsubscribe.
- [ ] 5.3 После появления базы PR #11/#12 в dev интегрировать человеческий TTY stderr hint; проверить JSON/plain/stdout/exit codes, quiet/non-TTY, help/version и отсутствие передачи данных после ошибки, обновить примеры диагностики.
- [ ] 5.4 Добавить README/help, резервный email, GitHub bug form и внешний `contact_links` с единым URL формы, предоставленным владельцем; проверить совпадение адресов, доступность формы без login и работу примеров в Docker; после появления standalone #14 проверить и описать этот формат отдельно.

## 6. Сквозная приёмка

- [ ] 6.1 Выполнить сквозной сценарий на test deployment с установленной App и пользователем без GitHub-аккаунта: receipt → moderation → ровно один issue → уточнение → resolution → release → ответ; сохранить отдельные доказательства live API и почты без private data.
- [ ] 6.2 Проверить end-to-end outage/retry/duplicate/delete-contact и резервный канал, выполнить применимые quality gates и `openspec validate add-user-feedback-loop --strict`, сверить все критерии #15/#32 и двустороннюю трассировку; сохранить результаты без приравнивания offline proof к работающему сервису.

## Workflow follow-up

- После реализации выполнить `openspec-verify-change`, затем `openspec-archive-change` и обновить путь change в #15 и #32 на фактический archive path.
- Отправить реализованную ветку на ревью в dev. Доступность GitHub шаблонов в main и deployment внешнего сервиса подтверждать отдельными release/deployment доказательствами.
