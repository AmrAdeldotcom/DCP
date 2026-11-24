# DCP Alpha 10 release-signing key

- Key ID: `dcp-alpha10-rsa3072-20261124`
- Algorithm: RSA-3072 with SHA-256 / PKCS#1 v1.5 signatures
- Public key: `release-public.pem` (safe to commit and embed in packages)
- Private key: stored as a base64 PEM value in the macOS login Keychain under account `DCP Alpha 10`, service `DCP-Alpha10-Release-Signing-Key`. No private key file is kept in the repository or project tree. A key inventory and public-key copy are maintained outside the repository at `/Volumes/Data/ChatGPT/DCP-KEYS`; the private key remains in Keychain to avoid an unnecessary plaintext copy.
- Public key SHA-256: `bd56b87af22d81bdff536dfa332d2cdc4bf27da7f387af84cb4bdd4d201fdd67`

Before a signed release can be published, add a GitHub Actions repository secret named `DCP_RELEASE_SIGNING_PRIVATE_KEY_B64` containing the base64 value stored in the Keychain. To place that value on the clipboard without printing it, run this locally on Amrs-Mac-Pro:

```bash
security find-generic-password -a 'DCP Alpha 10' -s 'DCP-Alpha10-Release-Signing-Key' -w | pbcopy
```

Then paste it into GitHub → `AmrAdeldotcom/DCP` → Settings → Secrets and variables → Actions. Never paste the value into chat, project files, commits, workflow logs or shell command arguments. The GitHub connector used by this automation is read-only for repository secrets, and `gh` is not installed on the Mac, so this secret has not been configured by automation.
