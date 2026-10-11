# Verification: fix-macos-first-launch

Issue: https://github.com/mesilov/bitrix24-cli/issues/23, milestone 0.1.0.
Implementation: `f91870fca9ed946df1012f8fb79d5e8a95805138`.
Conflict resolution against dev: `f627f34dc06afe055f0c610f06128af7ee0661e3`.

## Diagnosis and implementation

The original macOS artifact reports linker ad-hoc signature `micro.sfx`.
Its signature terminates at byte 54,885,128, while the combined executable
contains 63,869,275 bytes. `codesign --verify --strict` rejects this original
file. SPC combines runtime and PHAR by concatenating bytes after the signed
runtime; signing the completed file also requires explicit PHP micro payload
bounds so the final signature is excluded from PHAR reads.

Pinned sources inspected:

- [SPC combine command](https://github.com/crazywhalecc/static-php-cli/blob/5b5861c366a0d94bc84002db7b3f46144b388fbb/src/SPC/command/MicroCombineCommand.php).
- [PHP micro payload metadata](https://github.com/static-php/phpmicro/blob/fb6d497b6f4cf138ee3851a30c905d64b7b19aed/php_micro_fileinfo.c).
- [PHP micro bounded read/seek/stat hooks](https://github.com/static-php/phpmicro/blob/fb6d497b6f4cf138ee3851a30c905d64b7b19aed/php_micro_hooks.c).

Both source-patch replacements match the pinned `php_micro_fileinfo.c`
exactly once. The target-specific patch retains a 16-byte
`__DATA,__micro_sfxsize` section; a zero placeholder uses the existing fallback
before packaging, preserving SPC compiler smoke behavior. Completed artifacts
receive big-endian payload start/end values before final signing.

`build/macos.py` checks thin arm64 executable/load-command/segment/section
bounds, preserves the original PHAR offset and bytes, removes the obsolete
signature command, extends `__LINKEDIT` and signs the completed file with native
Apple `codesign`. Its verifier checks exact payload bytes, final signature at
EOF and strict signing. Packaging finalizes both application and runtime probe;
CI repeats strict verification before running existing runtime smoke and upload.

## Local checks

On macOS 15.5 build 24F74, arm64, security assessment enabled:

- `python3 build/tests/macos-signing-check.py`: 5 native tests passed.
  Real Apple signing and execution cover zero placeholder, invalid concatenated
  signature, successful finalization and payload execution, actual payload
  tampering, malformed/unsupported input without mutation, and unsigned tail rejection.
- `python3 build/tests/source-check.py`: 12 tests passed.
- `python3 build/tests/publish-check.py`: 7 tests passed.
- `actionlint -shellcheck=` and `git diff --check`: passed.
- `openspec validate fix-macos-first-launch --strict`: passed.

Conflict resolution preserves dev's collapsible Linux/macOS/Windows installation
blocks while updating macOS integrity/approval instructions. Source pins,
production PHP dependencies and application command behavior are unchanged.

## CI and downloaded package

Packaging run [38169460319](https://github.com/mesilov/bitrix24-cli/actions/runs/38169460319)
passed for source `f627f34dc06afe055f0c610f06128af7ee0661e3`: Linux amd64,
macOS arm64, Windows amd64 and release archive-set verification all succeeded.
PR #33 also passed PHPStan, PHP-CS-Fixer, Rector, license, unit and worktree checks.
The earlier implementation-only run 38168886519 was cancelled by the updated PR;
it is not used as passing packaging evidence.

The uploaded macOS application and probe pass strict signing and byte-for-byte
payload verification locally. The application reports
`dev-f627f34dc06afe055f0c610f06128af7ee0661e3`. The full local smoke of the
downloaded package passed version/help/list, usage exits, 30 task commands,
offline dry-run, missing connection, actual SIGINT, autoload, extensions,
Unicode, trusted HTTPS, rejected certificate, clean PATH and temporary cleanup.
A copy with one embedded-payload byte changed was rejected by strict signing
and the payload verifier; the original executable remained unchanged.

All three installation archives in the uploaded release-assets artifact pass
`shasum -a 256 --check SHA256SUMS`. Its macOS tar archive contains the same
executable as the platform artifact, SHA-256
`a2d472456fc3596b4313f45dc364a049f817b1854c92ab533e6b55edc9eb231b`.
README download links now identify this verified run rather than the original
invalid-signature build.

## Actual browser/quarantine acceptance

On macOS 15.5 build 24F74, arm64, a real Chrome download of
[artifact 11713796805](https://github.com/mesilov/bitrix24-cli/actions/runs/38169460319/artifacts/11713796805)
has ZIP SHA-256
`2d603d3308056859754a97030d978a15fe8cd7d27399f74b90576e108a6599c2`, matching
GitHub's artifact digest. Chrome assigned quarantine
`0281;6acbeba0;Chrome;CCF4440F-1A3F-4FE3-AB46-5647E439ABB7`.
Native `ditto` copying and `ditto -x -k --qtn` extraction preserved the
quarantine flags and provenance UUID; the executable's quarantine is
`0281;6acbee0a;;CCF4440F-1A3F-4FE3-AB46-5647E439ABB7`.

The first `--version` was killed by Gatekeeper (SIGKILL, shell-equivalent 137).
The system log identified a Gatekeeper denial, and System Settings displayed
the specific `b24cli` block with **Allow Anyway**. Strict integrity verification
already passed. After the user explicitly authorized that specific approval,
**Privacy & Security → Allow Anyway** removed the block. With normal macOS
execution outside Codex's sandbox and PATH `/usr/bin:/bin`, version, help and
list then all returned zero. Version matches the source above. Quarantine
remains present; macOS changes its flags from `0281` to `02c1` after approval
and execution, retaining the timestamp and provenance UUID. The executable
checksum remains unchanged, strict signing passes, and
`spctl --status` still reports `assessments enabled`.

The first post-approval command inside Codex's sandbox could not create the
default temporary file for PHAR decompression (255); repeating outside that
sandbox succeeded without changing the downloaded file. A separate
unquarantined API-download copy had passed local smoke before the browser test;
this is not a pristine-machine trust test. The browser-extracted file itself
had not been executed or approved before the recorded first attempt.
The exact observations and command outputs are retained in
[browser-acceptance.json](browser-acceptance.json).

This validates the documented specific-file approval path, not warning-free
distribution. Developer ID/notarization, public Release publication, managed
Mac policies and live portal operations are outside this change and have not
been performed. No global security setting was disabled or quarantine removed.

## Verification report

| Dimension | Result |
| --- | --- |
| Completeness | 7/7 tasks; both added requirements implemented |
| Correctness | 2/2 requirements and 4/4 scenarios covered by the evidence below |
| Coherence | Pinned source metadata, bounded finalization, native signing, existing packaging patterns and separate browser acceptance follow the design |

| Delta scenario | Implementation and evidence |
| --- | --- |
| Verify the completed executable | `build/macos.py`, `build/package.py`, packaging CI strict app/probe verification, actual payload comparison and local full smoke |
| Reject signed payload tampering | Native fixture regression and tampering of a downloaded actual application; strict signing and payload verifier reject it |
| Run a quarantined download | Actual Chrome archive, inherited quarantine, initial Gatekeeper denial, authorized per-file approval and zero exits for version/help/list |
| State production trust limits | README and build guide explain ad-hoc integrity, specific-file approval and absent Developer ID/notarization; this report separates CI and browser evidence |

No critical issues, warnings, suggestions or skipped checks remain within the
defined change. Strict OpenSpec validation passed before archive; the final
documentation link/structure and recorded-evidence checks passed after archive.
Existing unrelated dev changes and the dirty
primary checkout were preserved.
