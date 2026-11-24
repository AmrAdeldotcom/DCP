using System.IO.Compression;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;

namespace DCP;

internal sealed record WindowsUpdatePreparation(
    string WorkRoot,
    string Version,
    int PackageRevision,
    string ReleaseTag,
    string Strategy,
    int FileCount);

internal static class WindowsUpdateClient
{
    private const string Repository = "AmrAdeldotcom/DCP";
    private const string KeyId = "dcp-alpha10-rsa3072-20261124";
    private const string PublicKeySha256 = "bd56b87af22d81bdff536dfa332d2cdc4bf27da7f387af84cb4bdd4d201fdd67";
    private const long MaxArchiveBytes = 2L * 1024 * 1024 * 1024;
    private const long MaxFileBytes = 512L * 1024 * 1024;
    private const int MaxFiles = 200_000;

    private static readonly string[] RequiredReleaseAssets =
    [
        "DCP-Windows-x64.zip",
        "DCP-Linux-x64.tar.gz",
        "DCP-macOS-Intel.zip",
        "DCP-macOS-AppleSilicon.zip",
        "DCP-Windows-update.json",
        "DCP-Windows-update.json.sig",
        "DCP-Linux-update.json",
        "DCP-Linux-update.json.sig",
        "DCP-macOS-Intel-update.json",
        "DCP-macOS-Intel-update.json.sig",
        "DCP-macOS-AppleSilicon-update.json",
        "DCP-macOS-AppleSilicon-update.json.sig"
    ];

    internal sealed record UpdateFile(string path, long size, string sha256);
    private sealed record DownloadAsset(string Name, string Url, long Size, string Sha256);

    public static async Task<WindowsUpdatePreparation?> PrepareAsync(
        string installRoot,
        string currentVersion,
        int currentRevision,
        HttpClient client,
        CancellationToken cancellationToken)
    {
        var root = Path.GetFullPath(installRoot);
        var publicKeyPath = Path.Combine(root, "platform", "update", "release-public.pem");
        if (!File.Exists(publicKeyPath))
            throw new InvalidOperationException("The pinned DCP release public key is missing.");

        var publicKeyBytes = await File.ReadAllBytesAsync(publicKeyPath, cancellationToken);
        var fingerprint = Convert.ToHexString(SHA256.HashData(publicKeyBytes)).ToLowerInvariant();
        if (!CryptographicOperations.FixedTimeEquals(
                Encoding.ASCII.GetBytes(fingerprint),
                Encoding.ASCII.GetBytes(PublicKeySha256)))
            throw new InvalidOperationException("The DCP release public key fingerprint does not match the pinned key.");

        using var rsa = RSA.Create();
        rsa.ImportFromPem(Encoding.UTF8.GetString(publicKeyBytes));

        using var releaseDocument = await DownloadJsonAsync(
            client,
            $"https://api.github.com/repos/{Repository}/releases?per_page=30",
            4 * 1024 * 1024,
            cancellationToken);
        var release = FindCompleteRelease(releaseDocument.RootElement);
        if (release is null)
            return null;

        var tag = GetString(release.Value, "tag_name");
        if (!Regex.IsMatch(tag, @"^v\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$"))
            throw new InvalidOperationException("GitHub returned an invalid DCP release tag.");

        var releaseAssets = ReadReleaseAssets(release.Value, tag);
        JsonElement? referenceManifest = null;
        byte[]? windowsManifestBytes = null;

        var manifests = new[]
        {
            ("DCP-Windows-update.json", "windows", "x64"),
            ("DCP-Linux-update.json", "linux", "x64"),
            ("DCP-macOS-Intel-update.json", "macos", "x64"),
            ("DCP-macOS-AppleSilicon-update.json", "macos", "arm64")
        };

        foreach (var (name, platform, architecture) in manifests)
        {
            var manifestBytes = await DownloadSmallAssetAsync(client, releaseAssets[name], 2 * 1024 * 1024, cancellationToken);
            var signatureBytes = await DownloadSmallAssetAsync(client, releaseAssets[name + ".sig"], 16 * 1024, cancellationToken);
            if (!VerifyDetachedSignature(manifestBytes, signatureBytes, rsa))
                throw new InvalidOperationException($"Detached signature verification failed for {name}.");

            using var document = JsonDocument.Parse(manifestBytes, new JsonDocumentOptions { MaxDepth = 64 });
            ValidateManifest(document.RootElement, platform, architecture, tag, releaseAssets);
            var manifest = document.RootElement;
            if (referenceManifest is null)
            {
                referenceManifest = manifest.Clone();
            }
            else
            {
                foreach (var field in new[] { "version", "release_tag", "package_revision", "commit", "key_id" })
                {
                    var left = referenceManifest.Value.GetProperty(field);
                    var right = manifest.GetProperty(field);
                    var equal = field == "package_revision"
                        ? left.GetInt32() == right.GetInt32()
                        : string.Equals(left.GetString(), right.GetString(), StringComparison.Ordinal);
                    if (!equal) throw new InvalidOperationException($"Signed release manifests are not synchronized: {name} ({field} mismatch).");
                }
            }
            if (platform == "windows")
                windowsManifestBytes = manifestBytes;
        }

        if (windowsManifestBytes is null || referenceManifest is null)
            throw new InvalidOperationException("The synchronized signed Windows manifest is missing.");

        using var windowsDocument = JsonDocument.Parse(windowsManifestBytes, new JsonDocumentOptions { MaxDepth = 64 });
        var windowsManifest = windowsDocument.RootElement;
        var latestVersion = GetString(windowsManifest, "version");
        var latestRevision = windowsManifest.GetProperty("package_revision").GetInt32();
        if (!IsNewerRelease(latestVersion, currentVersion, latestRevision, currentRevision))
            return null;

        var delta = windowsManifest.GetProperty("delta");
        var useDelta = CanUseDelta(delta, currentVersion, currentRevision, releaseAssets);
        var strategy = useDelta ? "delta" : "full";
        var selectedAsset = useDelta ? delta.GetProperty("asset") : windowsManifest.GetProperty("full");
        var packageAsset = ParseAsset(selectedAsset);

        var workRoot = Path.Combine(Path.GetTempPath(), "DCP-update-" + Guid.NewGuid().ToString("N"));
        var payloadRoot = Path.Combine(workRoot, "payload");
        Directory.CreateDirectory(payloadRoot);
        try
        {
            var archivePath = Path.Combine(workRoot, packageAsset.Name);
            await DownloadPackageAsync(client, packageAsset, archivePath, cancellationToken);
            ExtractZipSafely(archivePath, payloadRoot, strategy == "full");

            var actualFiles = EnumerateStageFiles(payloadRoot);
            string[] deleted;
            if (useDelta)
            {
                var expectedFiles = ReadDeltaFiles(delta.GetProperty("files"));
                ValidateExactDeltaPayload(actualFiles, expectedFiles);
                deleted = ReadPathList(delta.GetProperty("deleted"));
            }
            else
            {
                deleted = ReadPathList(windowsManifest.GetProperty("full").GetProperty("deleted"));
                var stagedPaths = actualFiles.Select(file => file.path).ToHashSet(StringComparer.OrdinalIgnoreCase);
                if (deleted.Any(stagedPaths.Contains))
                    throw new InvalidOperationException("The signed full-package deletion list conflicts with a file in the full package.");
            }

            var normalized = new
            {
                schema = 2,
                version = latestVersion,
                package_revision = latestRevision,
                release_tag = tag,
                files = actualFiles,
                deleted
            };
            var manifestPath = Path.Combine(workRoot, "update-manifest.json");
            await File.WriteAllTextAsync(
                manifestPath,
                JsonSerializer.Serialize(normalized, new JsonSerializerOptions { WriteIndented = true }),
                new UTF8Encoding(false),
                cancellationToken);

            return new WindowsUpdatePreparation(workRoot, latestVersion, latestRevision, tag, strategy, actualFiles.Count);
        }
        catch
        {
            TryDeleteDirectory(workRoot);
            throw;
        }
    }

    private static JsonElement? FindCompleteRelease(JsonElement releases)
    {
        if (releases.ValueKind != JsonValueKind.Array)
            throw new InvalidOperationException("GitHub returned an invalid releases list.");

        foreach (var release in releases.EnumerateArray())
        {
            if (release.TryGetProperty("draft", out var draft) && draft.ValueKind == JsonValueKind.True)
                continue;
            if (!release.TryGetProperty("assets", out var assets) || assets.ValueKind != JsonValueKind.Array)
                continue;

            var names = assets.EnumerateArray()
                .Where(asset => asset.TryGetProperty("name", out var name) && name.ValueKind == JsonValueKind.String)
                .Select(asset => asset.GetProperty("name").GetString()!)
                .ToHashSet(StringComparer.OrdinalIgnoreCase);
            if (RequiredReleaseAssets.All(names.Contains))
                return release.Clone();
        }
        return null;
    }

    private static Dictionary<string, string> ReadReleaseAssets(JsonElement release, string tag)
    {
        var result = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
        foreach (var asset in release.GetProperty("assets").EnumerateArray())
        {
            var name = GetString(asset, "name");
            if (!name.StartsWith("DCP-", StringComparison.OrdinalIgnoreCase)) continue;
            var url = GetString(asset, "browser_download_url");
            var expected = OfficialAssetUrl(tag, name);
            if (!string.Equals(url, expected, StringComparison.Ordinal))
                throw new InvalidOperationException($"Release asset URL is not the exact official GitHub URL: {name}");
            if (!result.TryAdd(name, url))
                throw new InvalidOperationException("The DCP release contains duplicate asset names: " + name);
        }
        if (RequiredReleaseAssets.Any(name => !result.ContainsKey(name)))
            throw new InvalidOperationException("The selected DCP release is missing one or more synchronized platform assets.");
        return result;
    }

    private static async Task<JsonDocument> DownloadJsonAsync(HttpClient client, string url, int maxBytes, CancellationToken token)
    {
        var bytes = await DownloadSmallAssetAsync(client, url, maxBytes, token);
        return JsonDocument.Parse(bytes, new JsonDocumentOptions { MaxDepth = 64 });
    }

    private static async Task<byte[]> DownloadSmallAssetAsync(HttpClient client, string url, int maxBytes, CancellationToken token)
    {
        using var timeout = CancellationTokenSource.CreateLinkedTokenSource(token);
        timeout.CancelAfter(TimeSpan.FromSeconds(20));
        using var request = new HttpRequestMessage(HttpMethod.Get, url);
        request.Headers.UserAgent.ParseAdd("DCP-Desktop-Updater/2.0");
        request.Headers.Accept.ParseAdd("application/vnd.github+json");
        using var response = await client.SendAsync(request, HttpCompletionOption.ResponseHeadersRead, timeout.Token);
        response.EnsureSuccessStatusCode();
        if (response.RequestMessage?.RequestUri?.Scheme != Uri.UriSchemeHttps)
            throw new InvalidOperationException("DCP update metadata redirect did not remain on HTTPS.");
        if (response.Content.Headers.ContentLength is long length && length > maxBytes)
            throw new InvalidOperationException("DCP update metadata exceeds its maximum allowed size.");
        await using var input = await response.Content.ReadAsStreamAsync(timeout.Token);
        using var output = new MemoryStream();
        var buffer = new byte[32 * 1024];
        while (true)
        {
            var read = await input.ReadAsync(buffer, timeout.Token);
            if (read == 0) break;
            if (output.Length + read > maxBytes)
                throw new InvalidOperationException("DCP update metadata exceeds its maximum allowed size.");
            output.Write(buffer, 0, read);
        }
        return output.ToArray();
    }

    private static async Task DownloadPackageAsync(HttpClient client, DownloadAsset asset, string destination, CancellationToken token)
    {
        using var timeout = CancellationTokenSource.CreateLinkedTokenSource(token);
        timeout.CancelAfter(TimeSpan.FromMinutes(20));
        using var request = new HttpRequestMessage(HttpMethod.Get, asset.Url);
        request.Headers.UserAgent.ParseAdd("DCP-Desktop-Updater/2.0");
        using var response = await client.SendAsync(request, HttpCompletionOption.ResponseHeadersRead, timeout.Token);
        response.EnsureSuccessStatusCode();
        if (response.RequestMessage?.RequestUri?.Scheme != Uri.UriSchemeHttps)
            throw new InvalidOperationException("DCP package redirect did not remain on HTTPS.");
        if (response.Content.Headers.ContentLength is long length && length != asset.Size)
            throw new InvalidOperationException("DCP package download size header does not match the signed manifest.");

        await using var input = await response.Content.ReadAsStreamAsync(timeout.Token);
        await using var output = new FileStream(destination, FileMode.CreateNew, FileAccess.Write, FileShare.None, 1024 * 1024, true);
        using var hash = IncrementalHash.CreateHash(HashAlgorithmName.SHA256);
        var buffer = new byte[1024 * 1024];
        long total = 0;
        while (true)
        {
            var read = await input.ReadAsync(buffer, timeout.Token);
            if (read == 0) break;
            total += read;
            if (total > asset.Size || total > MaxArchiveBytes)
                throw new InvalidOperationException("DCP package download exceeded its signed size.");
            hash.AppendData(buffer, 0, read);
            await output.WriteAsync(buffer.AsMemory(0, read), timeout.Token);
        }
        await output.FlushAsync(timeout.Token);
        var digest = Convert.ToHexString(hash.GetHashAndReset()).ToLowerInvariant();
        if (total != asset.Size || !string.Equals(digest, asset.Sha256, StringComparison.OrdinalIgnoreCase))
            throw new InvalidOperationException("DCP package size or SHA-256 verification failed.");
    }

    private static JsonDocument ParseAndVerifyManifest(
        byte[] manifestBytes,
        byte[] signatureBytes,
        RSA rsa,
        string platform,
        string architecture,
        string tag,
        Dictionary<string, string> releaseAssets)
    {
        if (!VerifyDetachedSignature(manifestBytes, signatureBytes, rsa))
            throw new InvalidOperationException("DCP signed update manifest verification failed.");
        var document = JsonDocument.Parse(manifestBytes, new JsonDocumentOptions { MaxDepth = 64 });
        try
        {
            ValidateManifest(document.RootElement, platform, architecture, tag, releaseAssets);
            return document;
        }
        catch
        {
            document.Dispose();
            throw;
        }
    }

    internal static bool VerifyDetachedSignature(byte[] manifestBytes, byte[] signatureBytes, RSA rsa) =>
        rsa.VerifyData(manifestBytes, signatureBytes, HashAlgorithmName.SHA256, RSASignaturePadding.Pkcs1);

    internal static void ValidateManifest(
        JsonElement manifest,
        string platform,
        string architecture,
        string tag,
        Dictionary<string, string> releaseAssets)
    {
        if (manifest.ValueKind != JsonValueKind.Object || manifest.GetProperty("schema").GetInt32() != 3)
            throw new InvalidOperationException("Unsupported DCP signed manifest schema.");
        var version = GetString(manifest, "version");
        if (!Regex.IsMatch(version, @"^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$") ||
            GetString(manifest, "release_tag") != tag || tag != "v" + version)
            throw new InvalidOperationException("DCP manifest version and release tag do not match.");
        if (manifest.GetProperty("package_revision").GetInt32() < 1 ||
            GetString(manifest, "platform") != platform ||
            GetString(manifest, "architecture") != architecture ||
            !Regex.IsMatch(GetString(manifest, "commit"), @"^[a-fA-F0-9]{40}$") ||
            GetString(manifest, "key_id") != KeyId)
            throw new InvalidOperationException("DCP manifest platform, revision, commit or signing key metadata is invalid.");

        var full = manifest.GetProperty("full");
        var fullName = FullAssetName(platform, architecture);
        ValidateAsset(full, fullName, tag, releaseAssets);
        ValidatePathList(full.GetProperty("deleted"));

        var delta = manifest.GetProperty("delta");
        if (delta.ValueKind == JsonValueKind.Null) return;
        if (delta.ValueKind != JsonValueKind.Object)
            throw new InvalidOperationException("DCP delta metadata is invalid.");
        var baseData = delta.GetProperty("base");
        var baseVersion = GetString(baseData, "version");
        if (!Regex.IsMatch(baseVersion, @"^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$") ||
            GetString(baseData, "release_tag") != "v" + baseVersion ||
            baseData.GetProperty("package_revision").GetInt32() < 0)
            throw new InvalidOperationException("DCP delta base metadata is invalid.");
        // A signed delta may be omitted from a release; the full package remains
        // mandatory and is the deterministic fallback for that platform.
        ValidateAsset(delta.GetProperty("asset"), DeltaAssetName(platform, architecture), tag, releaseAssets, allowMissingFromRelease: true);
        var filePaths = ValidateDeltaFiles(delta.GetProperty("files"));
        var deleted = ValidatePathList(delta.GetProperty("deleted"));
        if (filePaths.Overlaps(deleted))
            throw new InvalidOperationException("DCP delta both replaces and deletes the same path.");
    }

    private static void ValidateAsset(
        JsonElement asset,
        string expectedName,
        string tag,
        Dictionary<string, string> releaseAssets,
        bool allowMissingFromRelease = false)
    {
        var name = GetString(asset, "name");
        var url = GetString(asset, "url");
        var size = asset.GetProperty("size").GetInt64();
        var hash = GetString(asset, "sha256");
        var expectedUrl = OfficialAssetUrl(tag, expectedName);
        var present = releaseAssets.TryGetValue(name, out var releaseUrl);
        if (name != expectedName || size < 1 || size > MaxArchiveBytes || !Regex.IsMatch(hash, @"^[a-fA-F0-9]{64}$") ||
            url != expectedUrl || (present && releaseUrl != expectedUrl) || (!present && !allowMissingFromRelease))
            throw new InvalidOperationException($"DCP signed asset metadata is invalid: {expectedName}");
    }

    private static HashSet<string> ValidateDeltaFiles(JsonElement files)
    {
        if (files.ValueKind != JsonValueKind.Array || files.GetArrayLength() > 5000)
            throw new InvalidOperationException("DCP delta file list is invalid.");
        var seen = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        foreach (var file in files.EnumerateArray())
        {
            var path = GetString(file, "path");
            ValidateUpdatePath(path);
            if (!seen.Add(path) || file.GetProperty("size").GetInt64() < 0 ||
                !Regex.IsMatch(GetString(file, "sha256"), @"^[a-fA-F0-9]{64}$"))
                throw new InvalidOperationException("DCP delta file metadata is invalid or duplicated: " + path);
        }
        return seen;
    }

    private static string[] ReadPathList(JsonElement paths)
    {
        ValidatePathList(paths);
        return paths.EnumerateArray().Select(item => item.GetString()!).ToArray();
    }

    private static HashSet<string> ValidatePathList(JsonElement paths)
    {
        if (paths.ValueKind != JsonValueKind.Array || paths.GetArrayLength() > 5000)
            throw new InvalidOperationException("DCP update deletion list is invalid.");
        var seen = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        foreach (var item in paths.EnumerateArray())
        {
            if (item.ValueKind != JsonValueKind.String)
                throw new InvalidOperationException("DCP update deletion entry must be a string.");
            var path = item.GetString()!;
            ValidateUpdatePath(path);
            if (!seen.Add(path))
                throw new InvalidOperationException("DCP update deletion list contains duplicate paths.");
        }
        return seen;
    }

    internal static void ValidateUpdatePath(string path)
    {
        if (string.IsNullOrWhiteSpace(path) || path.Contains('\\') || path.Contains(':') ||
            path.StartsWith("/", StringComparison.Ordinal) ||
            path.Any(character => character < 32 || character == 127) ||
            path.Split('/').Any(part => part.Length == 0 || part is "." or ".."))
            throw new InvalidOperationException("Unsafe DCP update path: " + path);
        foreach (var segment in path.Split('/'))
        {
            if (segment.EndsWith(' ') || segment.EndsWith('.') ||
                segment.IndexOfAny(new[] { '<', '>', '"', '|', '?', '*' }) >= 0 ||
                Regex.IsMatch(segment.Split('.')[0], @"^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9]|CONIN\$|CONOUT\$)$", RegexOptions.IgnoreCase))
                throw new InvalidOperationException("DCP update path contains a Windows-invalid or reserved file name: " + path);
        }
        var lower = path.ToLowerInvariant();
        if (lower == "dcp-userdata" || lower.StartsWith("dcp-userdata/", StringComparison.Ordinal) ||
            Regex.IsMatch(lower, @"^platform/(data|storage)(/|$)") ||
            Regex.IsMatch(lower, @"(^|/)\.env($|\.)"))
            throw new InvalidOperationException("DCP update path attempts to modify user data or environment secrets: " + path);
    }

    private static string NormalizeArchivePath(string raw)
    {
        if (string.IsNullOrWhiteSpace(raw) || raw.Contains('\\') || raw.Contains(':') ||
            raw.StartsWith("/", StringComparison.Ordinal) ||
            raw.Any(character => character < 32 || character == 127) ||
            raw.Split('/').Any(part => part.Length == 0 || part is "." or ".."))
            throw new InvalidOperationException("Unsafe path in DCP ZIP archive: " + raw);
        return raw;
    }

    internal static void ExtractZipSafely(string archivePath, string destination, bool fullPackage)
    {
        var root = Path.GetFullPath(destination);
        var rootPrefix = root.TrimEnd(Path.DirectorySeparatorChar) + Path.DirectorySeparatorChar;
        var seen = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        long totalBytes = 0;
        var fileCount = 0;
        using var archive = ZipFile.OpenRead(archivePath);
        foreach (var entry in archive.Entries)
        {
            var raw = entry.FullName.TrimEnd('/');
            var isDirectory = entry.FullName.EndsWith("/", StringComparison.Ordinal);
            if (raw.Length == 0) continue;
            var path = NormalizeArchivePath(raw);
            var unixType = (entry.ExternalAttributes >> 16) & 0xF000;
            if (unixType == 0xA000 || (unixType != 0 && unixType != 0x8000 && unixType != 0x4000))
                throw new InvalidOperationException("DCP ZIP contains a symbolic link or special entry: " + path);
            isDirectory = isDirectory || unixType == 0x4000;

            var isUserData = path.Equals("DCP-UserData", StringComparison.OrdinalIgnoreCase) ||
                path.StartsWith("DCP-UserData/", StringComparison.OrdinalIgnoreCase);
            if (isUserData)
            {
                if (fullPackage) continue;
                throw new InvalidOperationException("DCP delta archive attempted to include user data.");
            }
            ValidateUpdatePath(path);
            if (!seen.Add(path))
                throw new InvalidOperationException("DCP ZIP contains duplicate or case-colliding entries: " + path);

            var target = Path.GetFullPath(Path.Combine(root, path.Replace('/', Path.DirectorySeparatorChar)));
            if (!target.StartsWith(rootPrefix, StringComparison.OrdinalIgnoreCase))
                throw new InvalidOperationException("DCP ZIP path escapes its staging directory.");
            if (isDirectory)
            {
                Directory.CreateDirectory(target);
                continue;
            }
            if (entry.Length < 0 || entry.Length > MaxFileBytes)
                throw new InvalidOperationException("DCP ZIP file exceeds the per-file size limit: " + path);
            fileCount++;
            totalBytes = checked(totalBytes + entry.Length);
            if (fileCount > MaxFiles || totalBytes > MaxArchiveBytes)
                throw new InvalidOperationException("DCP ZIP exceeds extraction limits.");

            Directory.CreateDirectory(Path.GetDirectoryName(target)!);
            using var input = entry.Open();
            using var output = new FileStream(target, FileMode.CreateNew, FileAccess.Write, FileShare.None);
            input.CopyTo(output);
            if (output.Length != entry.Length)
                throw new InvalidOperationException("DCP ZIP extracted file size mismatch: " + path);
        }
    }

    internal static List<UpdateFile> EnumerateStageFiles(string stageRoot)
    {
        var result = new List<UpdateFile>();
        foreach (var path in Directory.EnumerateFiles(stageRoot, "*", SearchOption.AllDirectories))
        {
            var info = new FileInfo(path);
            if ((info.Attributes & FileAttributes.ReparsePoint) != 0)
                throw new InvalidOperationException("DCP staging tree contains a reparse point.");
            var relative = Path.GetRelativePath(stageRoot, path).Replace(Path.DirectorySeparatorChar, '/');
            ValidateUpdatePath(relative);
            using var stream = File.OpenRead(path);
            var hash = Convert.ToHexString(SHA256.HashData(stream)).ToLowerInvariant();
            result.Add(new UpdateFile(relative, info.Length, hash));
        }
        result.Sort((left, right) => StringComparer.OrdinalIgnoreCase.Compare(left.path, right.path));
        if (result.Count == 0 || result.Count > 5000)
            throw new InvalidOperationException("DCP staged package file count is invalid.");
        return result;
    }

    private static List<UpdateFile> ReadDeltaFiles(JsonElement files)
    {
        var result = new List<UpdateFile>();
        foreach (var file in files.EnumerateArray())
            result.Add(new UpdateFile(GetString(file, "path"), file.GetProperty("size").GetInt64(), GetString(file, "sha256").ToLowerInvariant()));
        return result;
    }

    internal static void ValidateExactDeltaPayload(List<UpdateFile> actual, List<UpdateFile> expected)
    {
        if (actual.Count != expected.Count)
            throw new InvalidOperationException("DCP delta archive does not contain exactly the signed file set.");
        var map = expected.ToDictionary(file => file.path, StringComparer.OrdinalIgnoreCase);
        foreach (var file in actual)
        {
            if (!map.TryGetValue(file.path, out var expectedFile) || file.size != expectedFile.size ||
                !string.Equals(file.sha256, expectedFile.sha256, StringComparison.OrdinalIgnoreCase))
                throw new InvalidOperationException("DCP delta archive content does not match its signed manifest: " + file.path);
        }
    }

    private static DownloadAsset ParseAsset(JsonElement asset) =>
        new(GetString(asset, "name"), GetString(asset, "url"), asset.GetProperty("size").GetInt64(), GetString(asset, "sha256").ToLowerInvariant());

    private static string FullAssetName(string platform, string architecture) => (platform, architecture) switch
    {
        ("windows", "x64") => "DCP-Windows-x64.zip",
        ("linux", "x64") => "DCP-Linux-x64.tar.gz",
        ("macos", "x64") => "DCP-macOS-Intel.zip",
        ("macos", "arm64") => "DCP-macOS-AppleSilicon.zip",
        _ => throw new InvalidOperationException("Unsupported DCP platform/architecture.")
    };

    private static string DeltaAssetName(string platform, string architecture) => (platform, architecture) switch
    {
        ("windows", "x64") => "DCP-Windows-x64-delta.zip",
        ("linux", "x64") => "DCP-Linux-x64-delta.tar.gz",
        ("macos", "x64") => "DCP-macOS-Intel-delta.zip",
        ("macos", "arm64") => "DCP-macOS-AppleSilicon-delta.zip",
        _ => throw new InvalidOperationException("Unsupported DCP platform/architecture.")
    };

    private static string OfficialAssetUrl(string tag, string name) =>
        $"https://github.com/{Repository}/releases/download/{tag}/{Uri.EscapeDataString(name)}";

    private static string GetString(JsonElement element, string property)
    {
        if (!element.TryGetProperty(property, out var value) || value.ValueKind != JsonValueKind.String)
            throw new InvalidOperationException($"DCP update metadata is missing string field '{property}'.");
        return value.GetString() ?? "";
    }

    internal static bool CanUseDelta(
        JsonElement delta,
        string currentVersion,
        int currentRevision,
        IReadOnlyDictionary<string, string> releaseAssets)
    {
        if (delta.ValueKind != JsonValueKind.Object || !delta.TryGetProperty("base", out var baseData)) return false;
        var deltaName = DeltaAssetName("windows", "x64");
        return releaseAssets.ContainsKey(deltaName)
            && string.Equals(GetString(baseData, "version"), currentVersion.TrimStart('v'), StringComparison.OrdinalIgnoreCase)
            && baseData.GetProperty("package_revision").GetInt32() == currentRevision;
    }

    internal static bool IsNewerRelease(string latestVersion, string currentVersion, int latestRevision, int currentRevision)
    {
        var comparison = CompareVersions(latestVersion, currentVersion);
        return comparison > 0 || (comparison == 0 && latestRevision > currentRevision);
    }

    internal static int CompareVersions(string left, string right)
    {
        static (Version Core, string Pre) Parse(string value)
        {
            value = value.Trim().TrimStart('v').Split('+')[0];
            var match = Regex.Match(value, @"^(\d+\.\d+\.\d+)(?:-([0-9A-Za-z.-]+))?$");
            if (!match.Success || !Version.TryParse(match.Groups[1].Value, out var version))
                throw new InvalidOperationException("Invalid semantic DCP version: " + value);
            return (version, match.Groups[2].Success ? match.Groups[2].Value : "");
        }

        var a = Parse(left);
        var b = Parse(right);
        var core = a.Core.CompareTo(b.Core);
        if (core != 0) return core;
        if (a.Pre.Length == 0) return b.Pre.Length == 0 ? 0 : 1;
        if (b.Pre.Length == 0) return -1;
        var leftParts = a.Pre.Split('.');
        var rightParts = b.Pre.Split('.');
        for (var index = 0; index < Math.Min(leftParts.Length, rightParts.Length); index++)
        {
            var leftNumeric = int.TryParse(leftParts[index], out var leftNumber);
            var rightNumeric = int.TryParse(rightParts[index], out var rightNumber);
            var part = leftNumeric && rightNumeric ? leftNumber.CompareTo(rightNumber) :
                leftNumeric ? -1 : rightNumeric ? 1 :
                string.Compare(leftParts[index], rightParts[index], StringComparison.Ordinal);
            if (part != 0) return part;
        }
        return leftParts.Length.CompareTo(rightParts.Length);
    }

    private static void TryDeleteDirectory(string path)
    {
        try { if (Directory.Exists(path)) Directory.Delete(path, true); } catch { }
    }
}
