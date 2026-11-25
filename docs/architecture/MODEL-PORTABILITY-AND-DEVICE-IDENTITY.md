# Model portability and device identity

Status: Architecture decision; device-clone rotation still requires implementation.

## Storage location

GGUF model files are stored in DCP's private user-data directory, not embedded in the DCP executable:
- Portable-first location: DCP-UserData/<OS>/storage/models/gguf/
- Manifest: models.json, with original display name, model metadata and SHA-256.
- Windows uses the portable directory beside the app when writable; the wrapper can fall back to the signed-in user's LocalAppData directory when the installation folder is not writable.

For a portable move, copy the DCP application folder together with its DCP-UserData folder. Copying only DCP.exe or DCP.app is not sufficient. If the Windows LocalAppData fallback is active, use a DCP export/import operation to move the model and user data rather than copying only the app folder.

Imported model bytes are content-addressed by SHA-256. The model content identity must remain the same on every device; do not rewrite the GGUF or mutate the model hash to identify a machine.

## Device identity

A device identity is not a model fingerprint and must not be derived from the model hash, device name, MAC address or other hardware identifiers.

Each DCP installation owns:
- A random device UUID.
- A device-local signing keypair.
- A Cloud registration bound to the public key and owner account.

When a complete DCP-UserData folder is copied to another machine, first-run clone detection must prevent the copied device identity and private signing key from being used as a second live device. The clone flow must:
1. Preserve GGUF files and model manifest.
2. Generate a new device UUID and signing keypair for the destination.
3. Keep the source installation's identity unchanged.
4. Mark the copied Cloud pairing as requiring a fresh owner approval/re-pair.
5. Keep local provider secrets protected; do not blindly copy secret stores across device boundaries. Require secure migration or reconnect providers.

This gives the desired behavior: the same model runs on the new device, while the new DCP installation has a different identity and must be paired separately.

## Security and acceptance

- Model SHA-256 is stable across devices for byte-identical GGUF.
- Device UUID and signing key are unique per installation.
- Cloning DCP-UserData never produces two active devices with the same signing identity.
- A clone keeps model files but requires Cloud re-pairing.
- Provider API keys and master keys are not silently duplicated.
- Moving the DCP folder and its portable user data preserves local models; moving only the executable does not.
