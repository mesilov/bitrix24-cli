# Spec Delta

## ADDED Requirements

### Requirement: Existing profile add rejection
`connection:add NAME` SHALL create a new profile only. An existing alias MUST produce configuration-error/exit 1 before webhook input, without applying supplied parameters, reading credentials or making API requests. The diagnostic MUST state that parameters were not applied and explain inspection and explicit replacement. The operation MUST preserve config.json, credential files and defaultProfile.

#### Scenario: Existing alias without redirected input
- **WHEN** `work` exists and `connection:add work --creator-id 3 -n` is invoked without `--webhook-stdin`
- **THEN** the CLI reports the duplicate profile as configuration-error/exit 1 without a webhook prompt or missing-input error
- **AND** the message states that defaults were not applied and identifies `connection:show work`, `connection:remove work`, `connection:add work` and reselection with `connection:use work` when required
- **AND** config.json and all credential files retain their bytes and references; defaultProfile and other profiles remain unchanged

#### Scenario: Existing alias with a stdin webhook
- **WHEN** `work` exists and a repeated add supplies `--webhook-stdin`, changed IDs and `--allows-time-tracking true`
- **THEN** stdin is not consumed for the webhook and existing credentials are not opened
- **AND** human, plain and JSON output report failure without fixture secrets or a success result; JSON retains the normal error contract and empty API-call metadata
- **AND** no profile metadata or credential file is created, replaced or removed

#### Scenario: Concurrent attempts to add the same alias
- **WHEN** two invocations begin adding an absent alias concurrently
- **THEN** at most one creates that profile and the other returns the duplicate failure without overwriting the winning profile
- **AND** the rejected operation does not leave an additional managed credential file

### Requirement: Documented profile replacement
Help for `connection:add`, README and the configuration guide SHALL explain that add does not update an existing profile. Instructions MUST show inspect, remove, add with all desired defaults and secret input, and use when restoring the selected default. They MUST explain that remove clears a matching defaultProfile and deletes its local credential without revoking the portal webhook.

#### Scenario: Discover replacement from help
- **WHEN** a user runs `b24cli help connection:add` without configured credentials
- **THEN** help explains create-only behavior and the replacement sequence without prompting, writing configuration or making API calls
- **AND** it explains that omitted defaults do not carry over from the deleted profile and that the webhook must be supplied again

#### Scenario: Add a missing creator default explicitly
- **WHEN** a user follows the documented replacement procedure for `work`, supplying the webhook again and `--group-id 187 --creator-id 3 --responsible-id 2 --allows-time-tracking true`
- **THEN** `connection:show work` reports `default.task.groupId=187`, `creatorId=3`, `responsibleId=2` as JSON integers and `allowsTimeTracking=true` as a JSON boolean
- **AND** an offline `task:add --profile work --title Test --dry-run --json` receives those defaults before required-field validation and makes no API calls
- **AND** a previously selected `work` becomes selected again only after the explicit `connection:use work`; other profiles remain unchanged

## MODIFIED Requirements

### Requirement: Secret input without argv
connection:add SHALL accept a webhook through hidden interactive input or explicitly requested `--webhook-stdin`. It MUST NOT provide a literal secret argument. Noninteractive missing input and competing stdin consumers MUST fail without waiting or exposing input.

#### Scenario: Hidden or piped input
- **WHEN** webhook input comes from a terminal or a secret file redirected to stdin with --webhook-stdin
- **THEN** interactive input is not echoed and piped input is read only when explicitly requested
- **AND** the secret does not enter subprocess argv

#### Scenario: Noninteractive missing input
- **WHEN** connection:add for a new alias runs with --no-interaction and no stdin option, or a second option also consumes stdin
- **THEN** usage-error/exit2 occurs without a prompt or partial profile creation
