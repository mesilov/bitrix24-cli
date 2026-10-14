# SDK and live portal investigation — 14 October 2026

Issue: https://github.com/mesilov/bitrix24-cli/issues/58

## Result

On the configured SDK playground portal, custom task values cannot be written through the tested REST3 add/update representations. Both documented `userFields` input and the SDK generic builder's flat `UF_*` input are rejected. REST1 custom update works; REST1 create works when required custom fields are supplied. Values were independently read back with REST1 and REST3.

This is a bounded live experiment with the existing string custom field `UF_AUTO_860796849253`. `UF_QUERY` is absent on this portal; its name/type and the issue author's portal have not been tested. No CLI implementation was changed and this investigation is not complete acceptance of the proposed change.

## SDK source

Installed the repository's locked SDK **3.7.0**, commit `8ebd4c154d5557db949b0196825f347a3a6c5bf0`, in the isolated worktree. Source inspection and runtime requests agree:

- `Services/AbstractItemBuilder.php:63`: `withUserField($userField, $value)` simply assigns `$this->fields[$userField] = $value`. It does not discover task-specific writability or convert a flat UF key into `userFields`.
- `Services/Task/Service/Task.php:84`: `add(array|TaskItemBuilder $fields)` builds the array and passes it as fields to Core with `ApiVersion::v3` (line 96).
- `Services/Task/Service/TaskItemSelectBuilder.php:581`: `userFields()` is a select builder method for reading. There is no dedicated `TaskItemBuilder::userFields()` setter in the locked SDK.
- `Legacy/Services/Task/Service/Task.php:62,163`: separate legacy add/update wrappers exist; update uses taskId and the supplied fields array. The probe selected API versions explicitly via SDK Core.

## Test context and requests

Portal: `bitrix24-php-sdk-playground.bitrix24.ru`; acting user **1**; configured test group **234**. Local date was 2026-10-14 (UTC run began 2026-10-13 20:06). Credentials were read from the existing environment file mounted read-only and were not placed in arguments or this evidence.

The raw request/response receipt is [2026-10-14-sdk-portal-probe.json](2026-10-14-sdk-portal-probe.json). It retains exact mutation parameters, safe exception details, field metadata, selected readback values and cleanup results.

| Case | Version | Observed result |
|---|---|---|
| SDK task add with title/creatorId/responsibleId/groupId | 3.0 | Created task **6260** |
| SDK builder withUserField(`UF_AUTO_860796849253`, value), then task add | 3.0 | `UnknownDtoPropertyException`: unknown field `uf_auto_860796849253` for taskdto |
| Task add with `userFields: [{key, value}]` | 3.0 | DTO validation: `Поле "userFields" не доступно к заполнению` |
| Task update 6260 with the same userFields list | 3.0 | Same field validation rejection |
| Task update 6260 with creatorId=1 | 3.0 | ACK result=true; this is a same-value update, not proof of changing the originator to another user |
| Task update 6260 with flat custom UF key | 1.0 | Confirmed task ID; exact submitted value read back through v1 and v3 |
| Task add with custom UF key, without other custom fields | 1.0 | Required custom fields string_req and int_req rejected |
| Task add with custom UF key plus required custom values | 1.0 | Created task **6262**; exact string and required values read back through v1 and v3 |

The corrected REST1 create supplied `UF_AUTO_411664353318` (required string) and `UF_AUTO_199893122420` (numeric, metadata type double) as well as the optional string field. No field definitions were created, edited or deleted.

## Editable metadata and the CLI failure

Default tasks.task.field.list and an explicit select returned the same relevant flags: **title=true, responsibleId=true, groupId=true, creatorId=false, userFields=false**. Therefore the issue author's report of false for every field is not reproduced on this portal.

Nevertheless, creatorId is accepted and required by the actual v3 creation request even with editable=false. Current CLI task:add --fields with those same ordinary fields and --dry-run returns usage-error/exit2 after one metadata read: `An advanced field is not confirmed editable by the portal metadata.` No mutation is attempted by that CLI invocation. This reproduces the advanced admission problem independently of custom values; field-list editable is insufficient as a universal add gate.

## Cleanup and limitations

- Both created tasks **6260 and 6262** were deleted with REST3 tasks.task.delete, returning result=true.
- Subsequent get requests were rejected, and a final REST1 tasks.task.list filtered by the unique probe title marker and creator returned **zero tasks**, with no next page. This also checked the titles used in rejected create attempts.
- Only the configured portal, acting user and selected scalar custom field were tested. Multiple fields, clearing, restricted roles, and UF_QUERY on the reporter's portal remain unverified.
- REST3 control creation did not enforce the two custom fields that REST1 create required. API-version differences in required custom validation must be documented when choosing a mixed creation route.
- `tasks.task.getFields` described custom names/types but did not expose multiplicity/mandatory flags in the observed response, and task.item.userfield.getlist returned an empty list. A plan requiring full multiplicity/clearing preflight solely from getFields needs another documented metadata source or an explicit supported-type boundary.

## Consequence for the existing proposal

The REST1 companion write approach is supported by this experiment; ordinary advanced editable admission remains defective. The current proposal still requires a future implementation and specification review. In particular, custom metadata completeness and required-field validation must not be treated as solved by these probes. No planning tasks are marked completed merely from this research, and no code/MR/CI readiness is claimed.
