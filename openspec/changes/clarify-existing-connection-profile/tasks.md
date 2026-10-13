# Tasks

## 1. Ранний отказ и сохранность профиля

- [x] 1.1 Добавить общую безопасную проверку существующего alias в ProfileRepository и вызвать её до webhook input в ConnectionCommandRunner; сохранить окончательную проверку под lock. Проверить duplicate с/без --webhook-stdin, -n, exit 1, configuration-error и отсутствие prompt/чтения тестового stdin/credential write через ConnectionProfilesTest.
- [x] 1.2 Добавить диагностике пояснение «параметры не применены» и show/remove/add/use; проверить human/plain/JSON, отсутствие fixture secret, пустой meta.apiCalls и неизменные bytes config/credentials/defaultProfile/другого профиля. Проверить repository-level duplicate и создание нового alias; сохранить проверку гонки под lock, а при заявлении concurrent acceptance приложить реальный concurrent fixture.
- [x] 1.3 Обновить help AddConnectionCommand с create-only семантикой и полной заменой; проверить help без credentials, filesystem writes и API requests, а также согласованность текста с duplicate diagnostic.

## 2. Документированный сценарий #59

- [x] 2.1 В README и docs/configuration.md описать inspection, доступ к webhook до удаления, remove/add с полным набором defaults и use для восстановления выбранного default; уточнить таблицу нового alias/дубликата. Проверить примеры с защищённым stdin либо hidden input и без секретов в argv, отсутствие обещания сохранения omitted defaults и миграционной истории в README.
- [x] 2.2 Проверить documented remove/add/use для groupId=187, creatorId=3, responsibleId=2, allowsTimeTracking=true: добавить regression в ConnectionProfilesTest для connection:show и task:add --profile work --title Test --dry-run --json. Подтвердить точные API-имена/типы, применение defaults до required validation, сохранность другого профиля и ноль API calls; проверить действующий отказ authorId и non-boolean значений без rename/coercion.

## 3. Общая проверка и доставка

- [x] 3.1 Выполнить make test-unit, make lint-all и openspec validate clarify-existing-connection-profile --strict; через openspec-verify-change сопоставить delta scenarios и проверки, сохранить результаты и границы доказательств в verification.md. Не считать локальные fixture checks доказательством release binary или live portal acceptance.
- [ ] 3.2 Проверить issue ↔ proposal, milestone 0.2.0 и label bug; перед implementation MR получить свежую origin/dev, разрешить расхождения без потери чужих изменений, выполнить необходимые повторные проверки и открыть/обновить PR в dev с актуальными результатами. Проверить base=dev, ссылку на #59, OpenSpec path и terminal CI status для точного commit.

## Workflow follow-up

- После подтверждённых merge в dev и закрытия #59 выполнить openspec-archive-change с синхронизацией delta, обновить OpenSpec path в issue и доставить архив в dev через отдельный MR, если требуется.
- Сохранить нужные файлы и commits, остановить окружение task worktree, перейти в сохраняемый checkout и удалить только /private/tmp/bitrix24-cli-issue-59 без --force; проверить git worktree list.
