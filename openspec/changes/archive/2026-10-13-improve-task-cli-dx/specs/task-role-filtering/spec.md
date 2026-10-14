# Spec Delta

## MODIFIED Requirements

### Requirement: Role filters compose with existing query criteria

Роль SHALL сочетаться с допустимыми критериями текущей команды через AND. `task:find` SHALL сочетать обязательный буквальный Unicode title поиск с id, responsibleId, groupId, status, due-before и where; `task:list` SHALL сохранять те же критерии без обязательного title. Роль SHALL оставаться отдельным критерием, не заменяя явные условия или меняя действующие конфликты обычных фильтров и where.

#### Scenario: Role and list criteria
- **WHEN** вызван `task:list --role auditor --group-id 42 --status in_progress`
- **THEN** выводятся только доступные задачи группы 42 со статусом in_progress, где текущий пользователь подтверждён как наблюдатель

#### Scenario: Role and advanced filter
- **WHEN** вызван `task:list --role creator --where '{"responsibleId":9}'`
- **THEN** возвращаются только задачи, созданные текущим пользователем и назначенные 9; where не заменяет роль

#### Scenario: Contradictory criteria
- **WHEN** выбран `--role responsible` и явный `--responsible` другого пользователя
- **THEN** критерии пересекаются через AND и дают полный пустой результат после полного разрешённого обхода

#### Scenario: Role and title search
- **WHEN** вызван `task:find --title "ДОГОВОР" --group-id 42 --role accomplice`
- **THEN** возвращаются только id/title задач с буквальным совпадением title без регистра Unicode, groupId 42 и подтверждённым текущим соисполнителем; дополнительно выбранные id/responsible/status/due-before/where SHALL пересекаться с title/groupId/ролью через AND с validation/conflicts task:list и прежним id/title output
