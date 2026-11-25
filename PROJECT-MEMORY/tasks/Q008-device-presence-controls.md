# Q008 paired-device presence pause/resume — DC33

Date: 2026-11-25
Repository: /Volumes/Data/ChatGPT/DCP
Branch: feature/local-ai-runtime-discovery

## Goal
Complete the paired-device inventory controls from the roadmap: show identity/platform/presence/scopes, allow presence pause/resume, and keep full revoke as a separate action.

## Behavior
- A pairing receives explicit scopes at approval time: presence and/or signaling.
- If presence was granted, either paired endpoint can pause or resume the shared presence view.
- Pausing hides the peer's online and last-seen fields in paired-device inventory.
- Pausing does not remove the pairing, change the granted scopes, or alter signaling permission.
- Resuming clears only presence_paused_at.
- Revoke remains the separate operation that removes the entire pairing.
- A pairing without the presence grant cannot be enabled by the pause/resume endpoint.
- The cloud endpoint is authenticated with the existing device token and rate limited.

## Implementation
- Added nullable device_pairings.presence_paused_at to new-install schema.
- Added an idempotent information_schema-based ALTER to schema.migrate.sql for existing installations.
- Cloud health now verifies that the column exists before returning schema=ok.
- Paired-device API now reports presence_granted, presence_paused and presence_enabled separately; it hides online/last_seen when presence is paused or not granted.
- Added POST /api/v1/paired-devices/{device_id}/presence with strict boolean validation, active-pair check, explicit presence-scope check, rate limit and a narrow update of only presence_paused_at.
- has_pair_scope now treats paused presence as unavailable without changing other scopes.
- Added CloudService::setPairingPresence and local POST /api/cloud/pairing-presence route.
- Devices UI renders Pause presence / Resume presence beside Revoke and explains that signaling permissions remain unchanged.
- Updated deployment notes for the migration and semantics.

## Validation
- PHP lint: Cloud index, CloudService, ApiController and mock router — PASS.
- Cloud contract tests — ALL CLOUD CONTRACT CHECKS PASSED.
- Cloud UI contract tests — ALL CLOUD UI CONTRACT CHECKS PASSED.
- Mock CloudService tests cover pause and resume responses.
- Full platform suite — ALL PLATFORM TESTS PASSED.
- JavaScript syntax — PASS.
- git diff --check — PASS.

## Safety / deployment
- No production Cloud database migration or upload was performed.
- The existing Cloud APP_KEY rotation still requires explicit user approval before production deployment.
- No live pairing state was changed.
- The SQL migration is committed for review but must be backed up and applied before the updated Cloud index.php is uploaded.
- Real Mac+Windows paired-device acceptance remains pending a production-safe deployment and explicit approval; local mock/contract coverage is green.
