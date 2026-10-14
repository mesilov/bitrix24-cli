# RC 0.2.0-rc-1 acceptance — 2026-10-14

Issue: https://github.com/mesilov/bitrix24-cli/issues/109

## Source and delivery

- PR #132 merged into dev at `5330662f4398e2d2a329d92070c0fa040833a4b3` after 13 successful checks and successful builds run 38534772781.
- RC source is `9c268e5bbb6b3c3cea85cfbb3726cbd8b5efbb99`, the already successful builds snapshot, reachable from dev and builds. The difference to merged dev affects four OpenSpec receipt/task files only; runtime, dependencies and build inputs are identical. The duplicate documentation-only intermediate run 38537161240 was cancelled.
- Annotated immutable tag `0.2.0-rc-1`: object `3a5f1098e088e32fee9940479e61973d18708583`, peeled commit equals the RC source above.
- [RC package run 38537920077](https://github.com/mesilov/bitrix24-cli/actions/runs/38537920077): all five jobs completed successfully (source gate, Linux, macOS, Windows, archives).
- [Existing artifact acceptance run 38541117982](https://github.com/mesilov/bitrix24-cli/actions/runs/38541117982): all three native jobs completed successfully; downloaded the existing RC archives without rebuilding.

## Downloads

- [All final archives and SHA256SUMS](https://github.com/mesilov/bitrix24-cli/actions/runs/38537920077/artifacts/11881912444), expires 2026-11-13.
- [Linux amd64 platform bundle](https://github.com/mesilov/bitrix24-cli/actions/runs/38537920077/artifacts/11882775204), expires 2026-10-21.
- [macOS arm64 platform bundle](https://github.com/mesilov/bitrix24-cli/actions/runs/38537920077/artifacts/11882065616), expires 2026-10-21.
- [Windows amd64 platform bundle](https://github.com/mesilov/bitrix24-cli/actions/runs/38537920077/artifacts/11881662781), expires 2026-10-21.

GitHub Actions downloads require a signed-in account with repository read access. The final asset bundle contains `b24cli-linux-amd64.tar.gz`, `b24cli-macos-arm64.tar.gz`, `b24cli-windows-amd64.zip`, and `SHA256SUMS`. Platform bundles also contain build diagnostics.

## Verified boundaries

For every native platform: all three archive checksums, required archive contents/licenses/permissions and corrupt-archive rejection; embedded version `0.2.0-rc-1`, exact source SHA and PHP 8.5.11; executable checksum; copied standalone binary in a Unicode directory outside checkout with a clean PATH and no credentials; version/about agreement; both root/list help forms; 56 public commands and 112 command-help calls; offline Unicode JSON dry-run; usage exit 2 and missing-connection exit 1 with zero API calls. Each platform executed 124 CLI invocations. macOS strict signature verification passed. Local downloaded archive verification also passed.

Machine-readable receipts and checksums are in `rc-0.2.0-rc-1-receipts/`. QA helper source was commit `f1c3404` on `feature/109-verify-rc-artifacts`. A temporary branch-only wrapper allowed dispatch through the registered packaging workflow; it is restored before delivery. The permanent manual workflow is `rc-artifact-acceptance.yml`.

Preflight history is preserved: initial QA run 38539070075 incorrectly selected the older bootstrap snapshot without #121 and failed the required-command assertion on all three platforms. Corrected preflight run 38539357395 selected source 9c268e5 and passed all three platforms. Actual RC acceptance above used the versioned RC archive set.

No live portal operations, browser-download quarantine/Gatekeeper installation, Developer ID notarization or Windows Authenticode acceptance were proved by these offline checks. About checks assert version consistency, not a complete editorial audit. Stable 0.2.0 source/documentation/skill acceptance remains pending; #134 and #136 are outside this frozen RC snapshot and skill issue mesilov/bitrix24-cli-skill#6 remains a stable-release dependency.

## Publication

Live Release readback on 2026-10-14 lists only published `0.1.0`, still latest. No RC Release was created. No promotion to main or stable tag/publication was performed. Issue #109 remains open and stable-release tasks remain unchecked.

## Current milestone readback

After the RC source was frozen, #134 and #136 were closed and their implementation merged into dev. At report delivery all 28 other milestone issues are closed; only #109 remains open. README archive PR #139 is still open. The current dev snapshot therefore requires a new RC to cover the newer README and weekday/unit changes; RC-1 stays immutable. Skill issue #6 is open and still refers to an older 46-command source catalog; update it against the final 56-command candidate.
