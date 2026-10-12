# Verification: resolve-main-rc-conflicts — 2026-10-12

Issue [#50](https://github.com/mesilov/bitrix24-cli/issues/50), affected promotion MR [#49](https://github.com/mesilov/bitrix24-cli/pull/49).

Refs: dev `ed09688e5d44b4c76bd13c01b21748a789ad96cd`, main `1fb709bd269ec815276f5e5ce75cca903f105cb4`. Resolved merge commit `84393ec` has both ancestors.

| Dimension | Result |
| --- | --- |
| Completeness | 3/3 local tasks; both content conflicts resolved and preservation checks passed. |
| Correctness | Spec-dependent checks not applicable (skip_specs: true). Entire merge commit Git tree is byte-identical to accepted dev tree; README and binary-distribution hashes unchanged. |
| Coherence | Main macOS clauses are an exact byte prefix of dev spec; main Gatekeeper/install paragraph appears unchanged in dev README. Obsolete pinned artifact links and future #19 wording were superseded by already accepted RC documentation. |

## Preservation and checks

- Four README conflict blocks were limited to old pinned artifact refs/expiry and outdated future profile defaults. Accepted dev paragraphs retained after reading base/main/dev. Main Gatekeeper paragraph and platform installation text preserved.
- Spec conflict was the two additional compression requirements versus an empty main block. Both main macOS requirements/all their scenarios remain exact; compression requirements retained with no duplicate headings.
- `git diff --name-only origin/dev HEAD`: empty after the resolved merge commit. All tracked files, including application/build/config/workflows/lock/licences/other evidence, are identical to accepted dev. Only new change bookkeeping is added subsequently.
- `git merge-base --is-ancestor origin/main HEAD`: PASS. No unmerged index entries or conflict markers in the two resolved documents.
- Strict OpenSpec **15/15** before this change's archive, local current Markdown targets/anchors and git diff --check PASS; repeated after final archive.
- Application/runtime tests were not rerun locally: no tracked tree/production input changed. Source-bound dev ed09688 already has 11 successful checks, including native matrix and actual archive verification; new CI runs are separate evidence.

No critical preservation/design issue remains. Helper PR delivery in dev and GitHub MR49 MERGEABLE are workflow follow-up; actual head/CI/outcome recorded in issue/PR once delivered. Main MR is not merged by this task; no release/tag or portal/fork action was performed.
