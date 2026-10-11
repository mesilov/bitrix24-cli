# Proposal

## Why

macOS arm64 development artifacts fail strict code-signature validation after `micro:combine` appends the PHAR outside the original Mach-O layout. Issue #23 records a quarantined first launch killed by macOS; repeating a successful launch does not establish a valid package for 0.1.0.

## What Changes

- Preserve explicit PHP micro payload start/end offsets while including the PHAR inside the final signed Mach-O layout.
- Apply a free ad-hoc signature to the completed macOS executable and require strict verification before artifact upload.
- Exercise payload integrity, tamper rejection, version/help/list and the existing offline runtime checks.
- Document downloaded development-artifact approval via macOS Privacy & Security and record browser/quarantine acceptance separately from runner-local smoke.
- Keep Developer ID/notarization as a separate production-distribution concern.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `binary-distribution`: valid final macOS signatures, preserved embedded payload, and downloaded-artifact first-launch evidence.

## Impact

macOS micro source patch, native packaging finalization, packaging tests and CI, README/build guide, and acceptance evidence. Linux/Windows command behavior and portal operations are unchanged. No paid certificate or global security-setting changes are required.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/23 — fix final Mach-O/PHAR integrity and verify/document macOS first launch; milestone 0.1.0.
- #24 already supplies PATH installation guidance; #25 researches paid production signing separately.
