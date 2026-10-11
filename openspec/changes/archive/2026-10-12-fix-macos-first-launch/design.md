# Design

## Context

See proposal.md for scope. The pinned SPC `MicroCombineCommand.php` concatenates `micro.sfx` and PHAR. The original `LC_CODE_SIGNATURE` ends at 54,885,128 in the inspected CI binary, while the complete file is 63,869,275 bytes. PHP micro falls back to `__LINKEDIT` end for payload start and supports big-endian start/limit values in `__DATA,__micro_sfxsize`.

## Goals / Non-Goals

Use the loader's existing bounded payload support and native Apple signing tools; do not redesign the PHP bootstrap or depend on paid credentials. Preserve source pins and record a small explicit source patch. Production notarization, other architectures and managed-policy overrides are outside #23.

## Decisions

1. Add a 16-byte macOS micro metadata section through a target-specific pinned-source patch. A zero placeholder falls back to the original runtime-size calculation, preserving compiler smoke tests before packaging. Once filled, the existing loader reads its explicit start/end bounds.
2. Finalize only thin arm64 Mach-O with validated load commands. Preserve the PHAR's original offset and bytes, remove the obsolete signature load command, clear its obsolete blob, and expand the final `__LINKEDIT` to include the payload. Fill the metadata before applying a new ad-hoc signature. Signing then appends the final signature beyond the payload. Recheck boundaries, payload digest and strict signature after signing.
3. Reject unfamiliar layouts, absent metadata, truncated inputs or inconsistent runtime/signature bounds rather than producing a partially repaired binary. Do not mutate the reusable compiled runtime when finalizing combined artifacts.
4. Native regression fixtures must show that strict signing passes, embedded PHP still executes with a bounded view, payload tampering fails and bad layouts fail without modifying inputs. Actual CI app/probe smoke covers PHAR integration and all existing runtime contracts.
5. Browser/quarantine evidence is collected on macOS 15.5 arm64 with the uploaded CI artifact, preserving normal Gatekeeper settings. Any per-file approval is recorded. CLI download or synthetic quarantine alone is insufficient.

## Risks / Trade-offs

- Mach-O metadata mistakes → bounded parser, reject unsupported layouts, real Apple codesign and executable tests, full native CI smoke.
- Signature bytes accidentally included in PHAR → explicit loader limit and byte comparison before/after signing.
- Linker discards unused section → retained source declaration, required metadata lookup and CI failure if missing.
- Apple download policy differs from runner → distinct real-browser acceptance; do not claim warning-free production distribution.

## Migration Plan

Publish verified development artifacts through the existing workflow, then merge the fix through dev and main as requested. Existing downloaded binaries must be replaced by a fresh artifact; PATH setup remains the documented #24 procedure. Rollback reverts the packaging change but does not establish old-binary validity.
