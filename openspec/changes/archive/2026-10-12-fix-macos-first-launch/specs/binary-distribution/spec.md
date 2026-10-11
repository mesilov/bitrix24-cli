# Spec Delta

## ADDED Requirements

### Requirement: Final macOS executable integrity
macOS arm64 artifacts SHALL carry a valid ad-hoc or Developer ID signature covering the final embedded application. Packaging MUST reject invalid signatures or changed payload bytes before uploading artifacts. The embedded application MUST retain its existing command and exit-code contracts after signing.

#### Scenario: Verify the completed executable
- **WHEN** the macOS runtime and application have been combined and finalized
- **THEN** `codesign --verify --strict` succeeds on both the application and runtime probe
- **AND** copied binaries execute version, help and list with the expected source identity
- **AND** the embedded PHAR matches the original payload byte for byte

#### Scenario: Reject signed payload tampering
- **WHEN** bytes inside a signed macOS application's embedded payload are modified
- **THEN** strict signature verification fails
- **AND** the altered executable is not accepted as a distributable artifact

### Requirement: Downloaded macOS development artifact first launch
Development-artifact documentation SHALL distinguish valid ad-hoc integrity from Developer ID/notarization trust and describe macOS approval of a specific trusted download. Acceptance MUST record an actual browser download with quarantine metadata and its first-launch/approval result on supported macOS separately from CI smoke. Global disabling of Gatekeeper MUST NOT be required.

#### Scenario: Run a quarantined download
- **WHEN** a supported macOS user downloads and extracts a verified development artifact through a browser with normal security settings
- **THEN** version, help and list work either immediately or after the documented Privacy & Security → Open Anyway approval for that file
- **AND** evidence records OS/build, source SHA, checksum, quarantine and approval result

#### Scenario: State production trust limits
- **WHEN** a user reads development-artifact installation instructions
- **THEN** the instructions explain that valid ad-hoc signing does not provide Developer ID/notarization trust
- **AND** runner-local success is not represented as downloaded-artifact acceptance
