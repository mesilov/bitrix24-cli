# Stable 0.2.0 release receipts — 2026-10-14

Issue #109. User explicitly authorized merge MR #143, final build and publication.

## Source identity

MR #143 merged into main at `6bba00c0599913a9f5cb0751bdf0d5dd7d7c489b`. Annotated immutable tag `0.2.0`: object `42d44f410fe391226352be0415beec13f709386c`, peeled source equals merged main. Runtime/application/dependencies/installer/build inputs match accepted RC2; final binaries are freshly built with stable version identity rather than renamed RC bytes.

Exact-source CI passed all unit/schema/quality jobs; unit run 38551707999 passed 825 tests / 13125 assertions. Production run https://github.com/mesilov/bitrix24-cli/actions/runs/38551708207 completed all six jobs successfully: main-tag validation, three native package/smoke jobs, archive verification and publication. Prepared consolidated Russian release notes are preserved by creating the draft before the production publish job.

## Verification boundaries

Stable three-platform native packaging smoke/archive checks, downloaded-artifact acceptance and public/latest/installer receipts are recorded separately. Existing RC1/RC2 evidence remains historical and does not replace final 0.2.0 checks. Full denied-role/file-membership/time-navigation portal proof and browser quarantine/Developer ID/notarization/Windows Authenticode remain explicitly bounded; no new Bitrix24 API behavior was changed in release preparation.

## Completion

Release https://github.com/mesilov/bitrix24-cli/releases/tag/0.2.0 was published 2026-10-14T11:03:40Z, draft=false, prerelease=false, latest=true. Its four public asset digests exactly match the native archive set and SHA256SUMS. Final archive/changelog delivery is through a documentation-only PR to dev; published tag/assets remain immutable.

## Final stable native and public acceptance

- Native downloaded stable artifacts: https://github.com/mesilov/bitrix24-cli/actions/runs/38554068826 — all three OS jobs success. Each verifies version 0.2.0/source6bba00c/PHP8.5.11, all three archive checksums/licenses/permissions/corruption rejection, copied native binary checksum, full about metadata, 56 commands, 112 command-help calls, 125 CLI invocations and 56 documented example definitions, outside checkout with Unicode/space paths and clean PATH/no credentials. Offline JSON/dry-run/usage/missing-connection checks have zero API calls. macOS strict signature passes.
- Native public Unix installation: https://github.com/mesilov/bitrix24-cli/actions/runs/38554073724 — Linux/macOS jobs success. Real published 0.2.0 archives verified through controlled release transport; repeat install, shell/profile/PATH and custom/no-modify-path scenarios passed. Anonymous public main installer explicit 0.2.0 and latest installs both printed b24cli 0.2.0 from outside checkout. Temporary branch-only workflow selects 0.2.0 in legacy installer harness, then is restored before delivery; installer code is unchanged. Windows copied-archive execution is verified, no Windows installer is claimed.
- Eight anonymous HTTPS probes (four version URLs + four latest URLs) returned HTTP200; public release API digests match the exact stable archive set. Source, tag, release metadata, native and installation receipts are in stable-0.2.0-receipts/.

All applicable release checks passed. Existing documented portal/signing/browser-quarantine limitations remain unchanged; no new portal claims or API-contract changes were made.
