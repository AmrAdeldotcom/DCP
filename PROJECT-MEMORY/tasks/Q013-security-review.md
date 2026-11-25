# Q013 security review — DC37

Date: 2026-11-25
Branch: feature/local-ai-runtime-discovery
Scope: local web entry/router, desktop asset serving, session/CSRF headers, Cloud transport, terminal path confinement, GGUF import, repository secrets hygiene.

## Findings fixed
1. Desktop PHP router previously returned false for any existing path, which could let PHP's built-in server attempt to serve files outside public/assets after path traversal. It now URL-decodes, resolves real paths, requires the resolved file to remain inside public/assets, and serves only an explicit static-extension allowlist. All other requests enter the app bootstrap.
2. Web preview previously set DCP_MODE but could inherit an unrelated DCP_USER_DATA_DIR from the host environment. It now forces DCP_USER_DATA_DIR to DCP_WEB_PREVIEW_DATA_DIR or the dedicated repository DCP-UserData/WebPreview default. A runtime test supplied a deliberately different inherited DCP_USER_DATA_DIR and verified the database/key were created only in the isolated WebPreview root; both temporary roots were removed.
3. Dynamic app and pairing pages now emit a per-response nonce-based Content-Security-Policy, object-src none, frame-ancestors none, form-action/base-uri self, nosniff, no-store, Referrer-Policy and a restrictive Permissions-Policy. The pairing page's inline script carries the nonce. Apache X-Frame-Options is now DENY.
4. CloudService now rejects plain HTTP except explicit localhost/127.0.0.1/::1 test endpoints, explicitly enables certificate-chain and hostname verification, and continues to disable redirects.
5. Repository ignore rules now exclude DCP-KEYS/ and .tmp/ to reduce accidental staging of private release material and disposable test harnesses.

## Runtime verification
- Desktop router served /assets/app.js with HTTP 200.
- Encoded traversal request to /%2e%2e/config/app.php was routed to the application, not served as config source; response body did not expose configuration values.
- Running DCP app page returned CSP nonce, Permissions-Policy, X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy same-origin and Cache-Control no-store.
- Pairing page returned a matching nonce attribute on its inline script.
- Web preview data isolation test passed with hostile inherited DCP_USER_DATA_DIR set.
- CloudService rejects http://cloud.example.com; local loopback mock endpoints remain allowed for tests.

## Secret hygiene
- No tracked private key, SQLite database, or DCP user-data key file found.
- platform/update/release-public.pem is the expected public verification key.
- platform/cloud/.env.example is a template, not a live secret.
- The private release signing key remains outside the repository at /Volumes/Data/ChatGPT/DCP-KEYS/release-private.pem with mode 0600 (2484 bytes); file contents were not displayed.
- The only private-key marker found by the filename-only scan is the validation marker string in platform/scripts/sign-update-manifest.sh, not a key value.

## Validation
- 61 PHP files linted successfully.
- JavaScript syntax checks pass for source JS files (generated gzip/build artifacts excluded).
- Security contract: ALL SECURITY CONTRACT CHECKS PASSED.
- Full platform suite: ALL PLATFORM TESTS PASSED.
- git diff --check passes.
- ShellCheck is not installed on this host; shell scripts were not ShellCheck-audited.

## Remaining risks / release gates
- The CSP still allows inline styles because existing UI markup uses style attributes; migrate these to classes before removing unsafe-inline from style-src.
- Web preview continues to rely on the documented Cloudflare Access deployment boundary; it is not a standalone public-account authentication system.
- Cloud APP_KEY rotation still requires explicit user approval; no production migration or upload was performed. Q008 live two-device acceptance remains blocked by that gate.
- Desktop Commander and DeepSeek Harness are still detection-only, not authenticated/connected lifecycle adapters.
- Signed release run 45515068071 failed before compilation in the macOS Intel SSE4.2 job because GitHub DNS could not resolve github.com while cloning llama.cpp. Other platform/runtime jobs passed; signed assembly/publication were skipped. Do not declare the release gate green.
- Full third-party dependency vulnerability audit was not performed in this pass.

Q013 remains active. Local security fixes and tests are pushed, but final release/security closeout remains blocked by the external CI fetch failure and explicit Cloud deployment approval.
