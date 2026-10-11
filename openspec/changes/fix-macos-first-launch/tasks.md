# Tasks

## 1. Payload-aware macOS signing

- [ ] 1.1 Record the pinned-source/layout diagnosis and add retained macOS payload metadata; verify the source patch matches the pin and zero placeholders preserve compiler smoke behavior.
- [x] 1.2 Implement bounded finalization and ad-hoc signing; verify real native fixtures preserve payload execution, reject malformed layouts and detect payload tampering.

## 2. Build gates and first-launch instructions

- [ ] 2.1 Integrate finalization for application/probe and strict verification before CI upload; verify build contract tests and native app/probe smoke.
- [ ] 2.2 Document specific-file macOS approval and production trust limits; verify commands and links against Apple guidance and the final package.

## 3. Artifact acceptance

- [ ] 3.1 Run the actual three-platform packaging CI and existing relevant checks; record run/source identities and results.
- [ ] 3.2 Download the uploaded macOS archive through a browser; record checksum, quarantine, OS/build and first-launch/approval followed by version/help/list.
- [ ] 3.3 Verify implementation against every delta scenario and all tracked tasks, with strict OpenSpec validation and no unreported acceptance gaps.

## Workflow follow-up

- Sync and archive the completed change and update the issue's archived OpenSpec path.
- Merge reviewed, passing PR into dev, then promote the verified change to main as requested.
