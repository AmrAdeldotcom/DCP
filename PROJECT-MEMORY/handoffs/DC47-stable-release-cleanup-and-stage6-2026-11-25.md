# DC47 — Stable release published, cleanup verified, Stage 6 continues

Timestamp: 2026-11-25T06:05:07+02:00
Repository: `/Volumes/Data/ChatGPT/DCP`
Working branch: `feature/local-ai-runtime-discovery`
Feature branch commit at checkpoint: `d3eed0c` (includes signed Device Agent work and cleanup hotfix merge)
Main: `d4fe3d1` (`fix(ci): paginate stable-release cleanup safely`)
Stable tag: `v1.0.0` → `cb3087e27c344af774ecf60aca40c98904859a85`

## Q005 stable-release result
- Release workflow `45538884432` completed SUCCESS.
- GitHub release `v1.0.0` is published as stable (`prerelease=false`) and latest, with 19 assets.
- Required full Windows/Linux/macOS packages, checksums and signed schema-3 update manifests/signatures were verified by the cleanup workflow preflight.
- Alpha 10 package was explicitly used as the stable Delta base; stable manifests retain full-package fallback.
- Legacy cleanup first partially removed old Alpha/Beta release/tag records but failed while paginating Actions runs. Root cause: the Actions endpoint returns an object containing `workflow_runs`, not an array suitable for the default pagination mapper.
- Fixed `.github/workflows/cleanup-legacy-github.yml` on main in commit `d4fe3d1`: explicit page loop, `workflow_dispatch`, push-repair trigger, stable-publication cutoff, and preservation of unrelated post-stable failures.
- Cleanup rerun `45544044653` completed SUCCESS. GitHub API now returns only stable release `v1.0.0`, no legacy Alpha/Beta tags, and no completed failures among the last 100 Actions runs. Previous cleanup failure is included in the targeted cleanup rule.

## Q005 remains open only for real installed-client acceptance
- Actual Windows, Ubuntu and macOS client upgrade/apply/rollback tests remain outstanding.
- Must verify that SQLite, `master.key`, settings, models/cache and user data survive the update and rollback.
- Do not mark Q005 done until these real target-device checks pass. CI/package publication is not a substitute for installed-client acceptance.

## Stage 6 progress
- Q012: persistent Task Inbox Queue MVP first slice is implemented locally and tested; safe checkpoint/context integration remains open.
- Q014: per-device workspace + Ed25519 signed Device Agent task/result transport integrated and end-to-end tested locally; legacy compatibility is preserved; real Windows/Ubuntu/macOS Device Agent acceptance remains open.
- Stage 6 is active and not complete.

## Git / CI
- Stage 6 source/docs commit: `ab3d5c6` on `feature/local-ai-runtime-discovery`.
- Cleanup hotfix: `d4fe3d1` on `main`.
- Feature branch merged the cleanup hotfix from origin/main locally as `d3eed0c`; the merge will be pushed with the DC47 memory checkpoint.
- Feature branch CI `45543895730`, `45543895807`, `45543895817`, `45543895868`, `45543896051` all completed SUCCESS; native macOS package test `45543895943` remained in progress at last check.
- Main push CI `45544044627`, `45544044678`, `45544044717`, `45544044752` completed SUCCESS; Linux distro compatibility `45544044637` remained in progress at last check.

## Next actions
1. Continue Q012 safe checkpoint coordinator design and tests without claiming mid-call interruption.
2. Run upgraded Device Agent on actual Windows, Ubuntu and macOS installs; verify signed task flow and workspace/key persistence.
3. Complete Q005 real client update/rollback/data-preservation acceptance.
4. Keep `main` at stable product code plus cleanup hotfix; keep ongoing Stage 6 feature work on `feature/local-ai-runtime-discovery` until cross-platform acceptance is complete.
