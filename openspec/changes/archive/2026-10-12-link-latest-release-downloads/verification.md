# Verification: link-latest-release-downloads — 2026-10-12

Issue [#55](https://github.com/mesilov/bitrix24-cli/issues/55). Baseline main `a49a1882b0eac4033ec67c08ae01eacf00e21567`, dev `5b738c0a71b69688b6823d352f4cebfd710ef20b` have identical trees. Main history merged into task branch without changing tracked baseline contents.

| Dimension | Result |
| --- | --- |
| Completeness | 3/3 local tasks verified: latest links, retained installation/dev context, docs-only preservation checks. Spec coverage not applicable (`skip_specs: true`). |
| Correctness | No changed CLI/API requirements/scenarios. All four exact asset URLs match published 0.1.0 assets and return expected version redirect plus HTTP 200. |
| Coherence | Matches design and [GitHub's documented URL format](https://docs.github.com/en/repositories/releasing-projects-on-github/linking-to-releases). Entire README from Установка onward byte-identical to baseline; only download section changes. |

## HTTP verification

Each URL uses `https://github.com/mesilov/bitrix24-cli/releases/latest/download/` and returned **302** to the same repository's `/releases/download/0.1.0/<asset>`, then **200** after following redirects with HEAD (no archive payload download):

| Asset | Version redirect | Final response |
| --- | --- | --- |
| b24cli-linux-amd64.tar.gz | 0.1.0 | 200 |
| b24cli-macos-arm64.tar.gz | 0.1.0 | 200 |
| b24cli-windows-amd64.zip | 0.1.0 | 200 |
| SHA256SUMS | 0.1.0 | 200 |

Latest API identifies public 0.1.0; annotated tag object remains `bffec827b7e7c78e6b9897961b4e2cd826c8b796`, package digests unchanged from publication verification. CDN signed URLs were not recorded.

## Local checks

- Current Markdown: **139 targets / 10 anchors**, no errors.
- Strict OpenSpec: **15/15** before archive and **14/14** after archive, no failures.
- README is the only modified tracked file before adding this change archive. Code/build/workflow/config/lock/licenses and historical records unchanged.
- Dev artifact source-SHA/auth/90-day retention and installation/checksum/macOS approval preserved. No stale RC candidate wording in the primary download section.
- Runtime tests not applicable to documentation links and not rerun locally. `git diff --check`, main ancestry and conflict-marker scan PASS.

No critical issues, warnings or suggestions in applicable checks. All 3/3 local tasks verified and change archived; task/main PR delivery and CI results are workflow follow-up. Published tag/assets are not rebuilt by this documentation edit.
