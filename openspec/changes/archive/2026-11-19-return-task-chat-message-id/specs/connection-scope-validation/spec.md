## MODIFIED Requirements

### Requirement: Only affected operations are blocked
Подтверждённая нехватка scope SHALL давать scope-missing/exit4 до первого вызова выбранной операции. Диагностика MUST содержать missing scopes, выбранный источник, дату проверки и способ исправления. Независимые команды MUST продолжать работу.

#### Scenario: Missing IM permission
- **WHEN** свежая проверка показывает task без im
- **THEN** task:chat:list/send/update/delete блокируются до чтения задачи/мутации, а обычный task:list выполняется
- **AND** диагностика предлагает добавить im вебхуку и вызвать auth:status

#### Scenario: Object denial and write safety
- **WHEN** scope достаточны, но API отказывает в доступе к объекту
- **THEN** сохраняется исходный permission-denied; изменяющий запрос не повторяется автоматически
- **AND** только точный insufficient_scope инвалидирует снимок для следующего обращения без повторения текущей операции
