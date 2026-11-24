using System.Text.Json;
using DCP;

static void Check(bool condition, string message)
{
    if (!condition) throw new InvalidOperationException("FAIL: " + message);
    Console.WriteLine("PASS: " + message);
}

const string tag = "v1.0.0-alpha.10";
const string keyId = "dcp-alpha10-rsa3072-20261124";
const string fullName = "DCP-Windows-x64.zip";
const string deltaName = "DCP-Windows-x64-delta.zip";
var fullUrl = $"https://github.com/AmrAdeldotcom/DCP/releases/download/{tag}/{fullName}";
var deltaUrl = $"https://github.com/AmrAdeldotcom/DCP/releases/download/{tag}/{deltaName}";
var fullHash = new string('a', 64);
var deltaHash = new string('b', 64);
var assets = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase) { [fullName] = fullUrl };
using (var apiRequest = new HttpRequestMessage(HttpMethod.Get, "https://api.github.com/repos/AmrAdeldotcom/DCP/releases"))
{
    Check(WindowsUpdateClient.TryAttachApiToken(apiRequest, "ghs_" + new string('A', 36)), "optional GitHub token is attached to api.github.com requests");
    Check(apiRequest.Headers.Authorization?.Scheme == "Bearer", "GitHub API token uses Bearer authorization");
}
using (var assetRequest = new HttpRequestMessage(HttpMethod.Get, "https://github.com/AmrAdeldotcom/DCP/releases/download/v1.0.0-alpha.10/package.zip"))
    Check(!WindowsUpdateClient.TryAttachApiToken(assetRequest, "ghs_" + new string('A', 36)), "GitHub API token is not forwarded to release asset hosts");

using var manifest = JsonDocument.Parse($$"""
{
  "schema": 3,
  "version": "1.0.0-alpha.10",
  "release_tag": "{{tag}}",
  "package_revision": 1,
  "platform": "windows",
  "architecture": "x64",
  "commit": "0123456789012345678901234567890123456789",
  "key_id": "{{keyId}}",
  "full": { "name": "{{fullName}}", "url": "{{fullUrl}}", "size": 1024, "sha256": "{{fullHash}}", "deleted": ["platform/app/obsolete.txt"] },
  "delta": {
    "base": { "version": "1.0.0-alpha.9", "release_tag": "v1.0.0-alpha.9", "package_revision": 0 },
    "asset": { "name": "{{deltaName}}", "url": "{{deltaUrl}}", "size": 256, "sha256": "{{deltaHash}}" },
    "files": [],
    "deleted": []
  }
}
""");

WindowsUpdateClient.ValidateManifest(manifest.RootElement, "windows", "x64", tag, assets);
Check(true, "schema 3 Windows manifest accepts a missing optional delta asset while requiring the full package");
Check(!WindowsUpdateClient.CanUseDelta(manifest.RootElement.GetProperty("delta"), "1.0.0-alpha.9", 0, assets), "missing delta asset selects full-package fallback even when base revision matches");
assets[deltaName] = deltaUrl;
Check(WindowsUpdateClient.CanUseDelta(manifest.RootElement.GetProperty("delta"), "1.0.0-alpha.9", 0, assets), "exact installed version and package revision select the delta asset");
Check(!WindowsUpdateClient.CanUseDelta(manifest.RootElement.GetProperty("delta"), "1.0.0-alpha.9", 1, assets), "revision mismatch selects full-package fallback");
Check(WindowsUpdateClient.IsNewerRelease("1.0.0-alpha.10", "1.0.0-alpha.9", 1, 0), "alpha.10 sorts newer than alpha.9");
Check(WindowsUpdateClient.IsNewerRelease("1.0.0-alpha.10", "1.0.0-alpha.10", 2, 1), "same semantic version uses packageRevision ordering");
Check(!WindowsUpdateClient.IsNewerRelease("1.0.0-alpha.10", "1.0.0-alpha.10", 1, 1), "same version and revision does not trigger an update loop");
Check(WindowsUpdateClient.CompareVersions("1.0.0", "1.0.0-alpha.10") > 0, "stable release sorts newer than prerelease");

var unsafeRejected = false;
try { WindowsUpdateClient.ValidateUpdatePath("DCP-UserData/Windows/data/dcp.sqlite"); }
catch (InvalidOperationException) { unsafeRejected = true; }
Check(unsafeRejected, "Windows update paths cannot target user databases or keys");
unsafeRejected = false;
try { WindowsUpdateClient.ValidateUpdatePath("platform/../../outside.txt"); }
catch (InvalidOperationException) { unsafeRejected = true; }
Check(unsafeRejected, "Windows update paths reject traversal segments");

using var badDeletion = JsonDocument.Parse(manifest.RootElement.GetRawText().Replace("platform/app/obsolete.txt", "DCP-UserData/Windows/data/dcp.sqlite", StringComparison.Ordinal));
var userDataRejected = false;
try { WindowsUpdateClient.ValidateManifest(badDeletion.RootElement, "windows", "x64", tag, assets); }
catch (InvalidOperationException) { userDataRejected = true; }
Check(userDataRejected, "signed full-package deletion lists cannot target user data");

Console.WriteLine("ALL WINDOWS UPDATE CLIENT CONTRACT TESTS PASSED");
