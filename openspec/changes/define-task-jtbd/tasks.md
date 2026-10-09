# Tasks

## 1. Фиксация согласованных требований

- [x] 1.1 Сохранить 16 согласованных JTBD и 3 сценария переписки с ID PM-01..06, AM-01..06, EMP-01..07; проверить 19 уникальных scenario IDs, WHEN/THEN, рабочую цель и признак успеха.
- [x] 1.2 Синхронизировать task-workflows в актуальные specs; проверить совпадение Purpose/Requirements с delta и выполнить openspec validate --specs --strict.
- [x] 1.3 Сохранить MCP inventory по 27 исходникам закреплённого commit; проверить уникальные зарегистрированные tool names, JSON/Markdown и legacy вызов comment.
- [x] 1.4 Сохранить потенциальные CLI-команды и матрицу полноты для всех 19 JTBD; проверить 55 уникальных имён (42 кандидата + 13 расширений), ссылки команд/сценариев, API/SDK маршруты и source paths, JSON/Markdown и количественные результаты.
- [x] 1.5 Закрепить первичный источник CLI Guidelines, сохранить cli-experience delta/main и согласовать entrypoint b24cli, singular namespace task и task:add, 55 имён Symfony Console с colon namespaces/flags/19 coverage references; проверить strict validation, JSON/Markdown, неизменность API mappings и полноты, прямые shell examples. Только planning, не реализация CLI.

- [x] 1.6 Зафиксировать 21 исключение из MVP в task-mvp-scope delta/main; разделить 59 команд на 28 MVP candidates, 10 API-policy-pending и 21 post-MVP с четырьмя явно согласованными REST 1.0 time routes; сверить отдельный MVP mapping всех 19 JTBD без изменения продуктовых требований/API evidence.

- [x] 1.7 Разделить checklist roots/items, добавить item:update и task:find --title; сверить 59 имён, 28/10/21 stages, bounded title search и parent/ID/SDK constraints, UX delta/main и обновлённые JTBD mappings. Только планирование.

## 2. Проектирование дальнейшей реализации

- [ ] 2.1 После MVP проектировать полное покрытие PM-02..05: provider blocker/change, ресурсные входы и dependency/project risk workflows. Эти исключённые workflow не блокируют retained CRUD/chat/file; native lifecycle gates остаются в 2.3. Задача не закрывается фактом переноса scope.
- [ ] 2.2 После MVP проектировать полное покрытие AM-02..05: принять provider clientRef/promisedAt/nextUpdate/delivery/feedback и решения согласования; проверить явные источники и раздельную внутреннюю/клиентскую приёмку по acceptance матрицы.
- [ ] 2.3 Для допуска оставшихся кандидатов MVP проверить lifecycle с needsControl/requireResult/правами и исключёнными result-командами без скрытого создания результата, bounded v3 list scans, четыре согласованные time routes с правами/принадлежностью записи и legacy policy для чек-листов/участников/истории. Сохранить evidence; blockers/change protocols после MVP не возвращаются сюда неявно. Наличие кандидата не заменяет runtime acceptance.
- [x] 2.4 Спроектировать чтение и отправку task chat для PM-06/AM-06/EMP-07 и остальных участников; проверить документированные маршруты REST3 send и IM history, scope/права, связь task/chat и отсутствие legacy comments; записать acceptance cases и явное ограничение strict-rest3. Runtime checks не выполнены.

## Workflow follow-up

- Реализацию подготовленных частей вести через отдельные OpenSpec changes, не отмечая продуктовые JTBD выполненными по наличию документов.
- Архивировать define-task-jtbd после завершения оставшихся задач проектирования и проверки соответствия; при этом явно сохранять границы runtime-доказательств.
