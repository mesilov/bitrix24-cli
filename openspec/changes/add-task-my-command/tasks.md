# Tasks

## 1. Query и фильтры

- [ ] 1.1 Добавить optional openOnly=false в TaskQuery и local scanner predicate с required status projection; unit проверить четыре открытых/два терминальных состояния, missing/unknown partial и неизменность query при false.
- [ ] 1.2 Добавить mapper task:my для явного positive --user, open/due-before/max-scan; unit проверить AND, строгую временную границу, null deadline, invalid input до credentials и scan cap/partial.

## 2. Команда и проверки

- [ ] 2.1 Реализовать MyTaskCommand через существующий ListTasksHandler, зарегистрировать CommandCatalog/routes; unit подтвердить offline help/list/completion, read-only strict-rest3, human/JSON/plain и актуальный inventory без потери других команд.
- [ ] 2.2 Добавить MyTaskCommandTest.php с Group command:task:my и собственным happy-path; проверить выбранного исполнителя/open/deadline на SDK fixtures, readback/cleanup и guard IntegrationCommandCoverageTest. Live skip не считать прохождением.
- [ ] 2.3 Обновить русскоязычный help/README, docs/cli-command-map.md и docs/task-jtbd.md для EMP-01 (ID, статус, файл теста, source snapshot/границы) и CHANGELOG #118; проверить примеры/ссылки и отсутствие обещания полной автоматической приоритизации.

## 3. Согласованная приёмка

- [ ] 3.1 Интегрировать свежую dev, пересчитать command inventory, выполнить целевые unit/integration/lint и strict OpenSpec; сохранить verification с отдельными local/native/live evidence и сверить issue ↔ change.

## Workflow follow-up

- Реализацию начинать только по отдельному запросу apply; этот PR содержит только planning.
- После подтверждённого merge/закрытия #118 sync/archive, обновить путь issue, сохранить архив в dev и удалить worktree.
