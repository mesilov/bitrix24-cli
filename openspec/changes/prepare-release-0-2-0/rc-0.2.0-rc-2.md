# RC 0.2.0-rc-2 acceptance — 2026-10-14

Issue: https://github.com/mesilov/bitrix24-cli/issues/109

## Frozen scope

Selected source/tag commit: `6931fd0440143878f6a19601bd87bbcfd51cbe70`; annotated tag object: `33e57a84b793648fbd7ca0ec7bdc76c07b3762bc`. RC1 remains unchanged on `9c268e5bbb6b3c3cea85cfbb3726cbd8b5efbb99`. Selected source includes #134 final README, #136 weekday seconds/hours/days and merged #139/#140. Milestone 0.2.0: 29 issues, 28 closed, only #109 open. Relative-date expansion #141 belongs to 0.3.0 and is excluded.

Intermediate builds run 38544589313 completed successfully on all five jobs before the RC tag was created. [RC package run 38545765746](https://github.com/mesilov/bitrix24-cli/actions/runs/38545765746) completed successfully: all five jobs, exact tag/source identity and all three native platform smoke gates passed.

## Source and delivery

Exact-source unit run 38545765741 passed 825 tests / 13125 assertions. All exact-source unit/schema/linters/worktree checks passed; records are in rc-0.2.0-rc-2-receipts/. Source help/about validation passed 5 tests / 588 assertions and CLI reference matched 56 public commands. All 242 relative README/guide links and HTML/heading anchors resolve.

PR #142 delivered stronger artifact checks and consolidated release notes into dev at 1e6c33268bef0d6fc6125bb1b2e3acd4b6ce6576. Runtime/application/dependency/build inputs are identical to frozen RC source; differences are QA tooling, receipts, OpenSpec and release notes. Final packaging caller is unchanged. Notes: docs/releases/0.2.0.md.

Skill PR #9 merged into main at 5c801852577082b21420445674f42fa9ac710593, issue #6 closed. It covers all 56 commands, #136 contract, 112 help calls, 35 examples and 42 source offline scenarios. Final RC2 version/source/catalog and native receipts were delivered into skill issue #6. Its original chat was archived before the final handoff; issue delivery provides the durable provenance.

## Evidence boundaries

Existing RC1 native preflight 38543837215 passed all three platforms with the strengthened verifier, 125 CLI calls and 56 example-definition checks per platform. It is verifier preflight, separate from RC2 acceptance.

Source checks, package smoke, copied-archive execution, manual/browser installation and portal proof are distinct. No full denied-role/file-membership/time-navigation portal proof is asserted. No Developer ID/notarization, Windows Authenticode or browser-quarantine installation is asserted for RC2. Public installer latest/download validation remains a stable-publication follow-up.

Release is not published; latest remains 0.1.0. No main promotion, stable 0.2.0 tag or RC Release is authorized by this preparation request. Issue #109 and stable artifact tasks remain open.

## Final RC2 native acceptance

[Acceptance run 38548634484](https://github.com/mesilov/bitrix24-cli/actions/runs/38548634484) completed successfully on Linux amd64, macOS arm64 and Windows amd64. Each runner downloaded the same three archives from exact package run 38545765746, verified all archive checksums/licenses/permissions/corruption rejection and its executable checksum, then copied and executed its native binary in a Unicode/space directory outside checkout with a clean PATH and no credentials. Exact version 0.2.0-rc-2, source SHA6931fd0 and PHP8.5.11 metadata match on all platforms. Each made 125 CLI calls, checked both help forms for 56 commands (112 calls), full about purpose/author/license/links with a missing dotenv path, README/map/raw/JSON catalog agreement and syntax of 56 documented examples; offline Unicode JSON dry-run/usage/missing-connection checks passed with zero API calls. macOS strict signature verification passed. Receipts and identical SHA256SUMS are stored in rc-0.2.0-rc-2-receipts/.

Download final [three archives and SHA256SUMS](https://github.com/mesilov/bitrix24-cli/actions/runs/38545765746/artifacts/11885960685), expires 2026-11-13. Platform diagnostic bundles (expire 2026-10-21): [Linux](https://github.com/mesilov/bitrix24-cli/actions/runs/38545765746/artifacts/11885875758), [macOS](https://github.com/mesilov/bitrix24-cli/actions/runs/38545765746/artifacts/11885152155), [Windows](https://github.com/mesilov/bitrix24-cli/actions/runs/38545765746/artifacts/11885222730). Signed-in repository read access is needed for Actions downloads.

## Requested main MR

[MR #143 dev → main](https://github.com/mesilov/bitrix24-cli/pull/143) was opened as draft pending RC2 acceptance. Documentation conflicts with earlier main installer delivery were reconciled in [PR #144](https://github.com/mesilov/bitrix24-cli/pull/144), merged into dev at 1534ebfbf4c7b849e26f04806ba05cea6486c944 after 13 successful checks. The implementation/installer/build inputs remain identical to selected RC2 source; only release docs/tooling/receipts changed. Main fd86141 is an actual parent of merge commit 0d47c23, followed by reviewed head 7a9a2c0; GitHub compare main...branch returned ahead/behind_by=0, and git merge-tree returned tree 71c857aa75141d0fcdbb228f401d54bdf7f6447e with no conflicts. The reviewer ancestry finding was disproved by local Git and GitHub APIs and resolved with graph receipts in the PR body. Stale active installer planning from main was removed because its completed archive already preserves all final public receipts.

MR #143 will be marked ready once this report is delivered and its exact updated head CI passes. Main is not merged and stable tag/Release publication remain unperformed. This preparation does not close issue #109 or mark final stable artifact tasks complete.
