## ADDED Requirements

### Requirement: Russian user-facing CLI help
The CLI SHALL render all user-facing help in Russian: headings, command purposes, argument and option descriptions, default/repeatability annotations, examples and limitations. This covers root help and every public command, including `completion`, `help` and `list`. Exact command, flag, argument, API/JSON identifiers, enum values, URLs, author and license names SHALL retain their spelling.

#### Scenario: Root overview uses Russian prose
- **WHEN** a user invokes `b24cli --help` or `b24cli -h`
- **THEN** the overview, headings, explanatory usage placeholders, global option descriptions and example explanations are in Russian
- **AND** executable examples retain valid CLI syntax

#### Scenario: Every public command supports both Russian help forms
- **WHEN** a user invokes `b24cli COMMAND --help` or `b24cli help COMMAND` for any registered public command
- **THEN** its purpose, usage headings, argument/option descriptions, default/repeatability annotations and help explanations are in Russian
- **AND** builtin commands and commands outside the task namespace follow the same language contract
- **AND** command-specific help retains its own scope, including help for `list`

#### Scenario: Human metadata in supported help formats is localized
- **WHEN** a user requests a supported non-text help format for a public command
- **THEN** human descriptions use the same Russian command metadata
- **AND** format schemas, field names and identifiers are preserved

#### Scenario: Help localization preserves offline behavior and completion scripts
- **WHEN** a user requests help without credentials or generates a supported shell completion script
- **THEN** help remains available without remote calls or configuration writes
- **AND** exit status, ANSI/non-TTY behavior and quiet/silent semantics are preserved
- **AND** completion emits valid shell code with unchanged command and option names

### Requirement: One common command group in the root overview
The root help SHALL group the current registered public command catalog before rendering it. It SHALL show exactly one group named «Общие», containing `completion`, `help`, `list` and any other public command without a namespace. «Общие» SHALL precede alphabetically ordered namespace groups; commands within each group SHALL be sorted by name. Hidden commands SHALL be excluded and each visible command and group SHALL appear once.

#### Scenario: Standard commands are displayed together
- **WHEN** a user invokes root help with the standard command catalog
- **THEN** `completion`, `help` and `list` appear together under the single heading «Общие»
- **AND** namespace groups follow without reopening «Общие»

#### Scenario: Additional public and hidden commands are registered
- **WHEN** root help runs after registering another public unnamespaced command, namespace commands and a hidden command
- **THEN** the public unnamespaced command joins «Общие» and namespace commands join their corresponding groups
- **AND** groups and commands follow the specified order without duplicates
- **AND** the hidden command is absent
