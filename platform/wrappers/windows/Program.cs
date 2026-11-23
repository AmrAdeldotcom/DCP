using System.Diagnostics;
using System.Net;
using System.Net.Sockets;
using System.IO.Compression;
using System.Reflection;
using System.Security.Cryptography;
using System.Text.Json;
using System.Text.RegularExpressions;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;

namespace DCP;

internal static class Program
{
    [STAThread]
    private static void Main()
    {
        ApplicationConfiguration.Initialize();
        Application.Run(new MainWindow());
    }
}

internal sealed class MainWindow : Form
{
    private int Port { get; set; }
    private const string Repository = "AmrAdeldotcom/DCP";
    private readonly WebView2 view = new() { Dock = DockStyle.Fill };
    private readonly string root = AppContext.BaseDirectory.TrimEnd(Path.DirectorySeparatorChar);
    private Process? php;
    private readonly HttpClient updaterClient = new() { Timeout = TimeSpan.FromSeconds(8) };
    private CancellationTokenSource? updaterCancellation;
    private string LogPath => Path.Combine(root, "DCP-UserData", "Windows", "storage", "logs", "desktop-wrapper.log");

    public MainWindow()
    {
        Text = "DCP — Desktop Connector Platform";
        Width = 1280;
        Height = 840;
        MinimumSize = new Size(980, 680);
        try { Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath); } catch { }
        Controls.Add(view);
        Load += StartDcp;
        FormClosing += StopDcp;
    }

    private async void StartDcp(object? sender, EventArgs e)
    {
        Directory.CreateDirectory(Path.GetDirectoryName(LogPath)!);
        Port = FindAvailablePort();
        AppendLog($"DCP startup: launching an isolated local PHP service on 127.0.0.1:{Port}. Remote update check is deferred to background.");

        var platform = Path.Combine(root, "platform");
        var publicDir = Path.Combine(platform, "public");
        var router = Path.Combine(publicDir, "router.php");
        var phpPath = FindCompatiblePhp(platform);

        if (!Directory.Exists(publicDir) || !File.Exists(router))
        {
            ShowStartupError("The DCP platform files are missing. Reinstall DCP from the official GitHub release.");
            return;
        }

        if (phpPath is null)
        {
            ShowStartupError("No compatible PHP runtime was found. Run platform\\dcp-env\\scripts\\bootstrap-windows.ps1; DCP will not overwrite an existing PHP installation.");
            return;
        }

        var userData = Path.Combine(root, "DCP-UserData", "Windows");
        Directory.CreateDirectory(Path.Combine(userData, "data"));
        Directory.CreateDirectory(Path.Combine(userData, "storage"));
        var start = new ProcessStartInfo
        {
            FileName = phpPath,
            WorkingDirectory = platform,
            UseShellExecute = false,
            CreateNoWindow = true,
            RedirectStandardOutput = true,
            RedirectStandardError = true
        };
        start.ArgumentList.Add("-S");
        start.ArgumentList.Add($"127.0.0.1:{Port}");
        start.ArgumentList.Add("-t");
        start.ArgumentList.Add(publicDir);
        start.ArgumentList.Add(router);
        start.Environment["DCP_MODE"] = "desktop";
        start.Environment["DCP_USER_DATA_DIR"] = userData;

        try
        {
            php = Process.Start(start);
            if (php is null)
            {
                ShowStartupError("Windows could not start DCP's private PHP service. Check the desktop-wrapper log.");
                return;
            }
            php.OutputDataReceived += (_, args) => { if (args.Data is not null) AppendLog("[PHP] " + args.Data); };
            php.ErrorDataReceived += (_, args) => { if (args.Data is not null) AppendLog("[PHP error] " + args.Data); };
            php.BeginOutputReadLine();
            php.BeginErrorReadLine();
            AppendLog("Started private PHP service, PID " + php.Id);
        }
        catch (Exception ex)
        {
            AppendLog("PHP startup failed: " + ex);
            ShowStartupError("DCP could not start its private PHP service. Details are in DCP-UserData/Windows/storage/logs/desktop-wrapper.log.");
            return;
        }

        for (var attempt = 0; attempt < 30; attempt++)
        {
            if (await IsServerReadyAsync())
            {
                try
                {
                    var webViewData = Path.Combine(
                        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                        "DCP-UserData", "WebView2");
                    Directory.CreateDirectory(webViewData);
                    var environment = await CoreWebView2Environment.CreateAsync(null, webViewData);
                    await view.EnsureCoreWebView2Async(environment);
                    view.CoreWebView2.Settings.AreDefaultContextMenusEnabled = true;
                    view.CoreWebView2.NavigationStarting += (_, args) =>
                    {
                        if (!IsInternal(args.Uri))
                        {
                            args.Cancel = true;
                            OpenExternal(args.Uri);
                        }
                    };
                    view.CoreWebView2.NewWindowRequested += (_, args) =>
                    {
                        args.Handled = true;
                        if (!IsInternal(args.Uri))
                            OpenExternal(args.Uri);
                    };
                    view.CoreWebView2.Navigate($"http://127.0.0.1:{Port}/");
                    AppendLog("DCP desktop UI loaded.");
                    updaterCancellation = new CancellationTokenSource();
                    _ = CheckAndApplyUpdateInBackgroundAsync(updaterCancellation.Token);
                    return;
                }
                catch (Exception ex)
                {
                    AppendLog("WebView2 initialization failed: " + ex);
                    ShowStartupError("DCP could not initialize Microsoft Edge WebView2. Install or repair the WebView2 Runtime, then restart DCP.");
                    return;
                }
            }

            if (php is { HasExited: true })
            {
                AppendLog("PHP exited unexpectedly with code " + php.ExitCode);
                break;
            }
            await Task.Delay(200);
        }

        ShowStartupError("DCP PHP service did not become ready. Check DCP-UserData\\Windows\\storage\\logs\\desktop-wrapper.log.");
    }

    private async Task CheckAndApplyUpdateInBackgroundAsync(CancellationToken cancellationToken)
    {
        string? workRoot = null;
        string? runnerPath = null;
        try
        {
            using var request = new HttpRequestMessage(HttpMethod.Get, $"https://api.github.com/repos/{Repository}/releases?per_page=10");
            request.Headers.UserAgent.ParseAdd("DCP-Desktop-Updater/1.1");
            request.Headers.Accept.ParseAdd("application/vnd.github+json");
            using var response = await updaterClient.SendAsync(request, HttpCompletionOption.ResponseHeadersRead, cancellationToken);
            response.EnsureSuccessStatusCode();
            using var stream = await response.Content.ReadAsStreamAsync(cancellationToken);
            using var releases = await JsonDocument.ParseAsync(stream, cancellationToken: cancellationToken);
            if (releases.RootElement.ValueKind != JsonValueKind.Array)
                throw new InvalidOperationException("GitHub returned an invalid releases list.");
            var requiredReleaseAssets = new HashSet<string>(StringComparer.OrdinalIgnoreCase)
            {
                "DCP-Windows-x64.zip",
                "DCP-Windows-update.json",
                "DCP-macOS-universal.zip",
                "DCP-Linux-x64.tar.gz",
                "DCP-Linux-x64.tar.gz.sha256"
            };
            var release = releases.RootElement.EnumerateArray().FirstOrDefault(item =>
            {
                if ((item.TryGetProperty("draft", out var draft) && draft.GetBoolean()) ||
                    !item.TryGetProperty("assets", out var assets) || assets.ValueKind != JsonValueKind.Array)
                    return false;
                var names = assets.EnumerateArray()
                    .Select(asset => asset.TryGetProperty("name", out var name) ? name.GetString() ?? string.Empty : string.Empty)
                    .Where(name => name.Length > 0)
                    .ToHashSet(StringComparer.OrdinalIgnoreCase);
                return requiredReleaseAssets.IsSubsetOf(names);
            });
            if (release.ValueKind == JsonValueKind.Undefined)
            {
                AppendLog("Background release check: no complete synchronized Windows/macOS/Linux release was found; keeping installed version.");
                return;
            }
            var tag = release.GetProperty("tag_name").GetString() ?? "";
            var current = Assembly.GetExecutingAssembly().GetCustomAttributes<AssemblyMetadataAttribute>()
                .FirstOrDefault(x => string.Equals(x.Key, "DCPVersion", StringComparison.OrdinalIgnoreCase))?.Value ?? "0.0.0";
            if (!IsNewerRelease(tag, current))
            {
                AppendLog($"Background release check: installed {current}; latest compatible release {tag}; up to date.");
                return;
            }

            var manifestAsset = release.GetProperty("assets").EnumerateArray()
                .FirstOrDefault(x => string.Equals(x.GetProperty("name").GetString(), "DCP-Windows-update.json", StringComparison.OrdinalIgnoreCase));
            if (manifestAsset.ValueKind == JsonValueKind.Undefined)
                throw new InvalidOperationException($"Release {tag} does not include DCP-Windows-update.json; full-package fallback is not configured in this updater.");

            var manifestUrl = manifestAsset.GetProperty("browser_download_url").GetString()
                ?? throw new InvalidOperationException("Update manifest URL is empty.");
            using var manifestResponse = await updaterClient.GetAsync(manifestUrl, cancellationToken);
            manifestResponse.EnsureSuccessStatusCode();
            using var manifestStream = await manifestResponse.Content.ReadAsStreamAsync(cancellationToken);
            using var manifest = await JsonDocument.ParseAsync(manifestStream, cancellationToken: cancellationToken);
            if (manifest.RootElement.GetProperty("schema").GetInt32() != 1)
                throw new InvalidOperationException("Unsupported update manifest schema.");

            var files = manifest.RootElement.GetProperty("files").EnumerateArray().ToArray();
            if (files.Length == 0 || files.Length > 5000)
                throw new InvalidOperationException("Update manifest file count is invalid.");

            // Merge every delta after the installed version. The newest manifest wins
            // for paths changed more than once; all URLs point at the latest source tag.
            var requiredFiles = new Dictionary<string, (string Hash, long Size)>(StringComparer.OrdinalIgnoreCase);
            foreach (var item in files)
            {
                var path = item.GetProperty("path").GetString() ?? "";
                var hash = item.GetProperty("sha256").GetString() ?? "";
                var size = item.GetProperty("size").GetInt64();
                if (!requiredFiles.TryAdd(path, (hash.ToLowerInvariant(), size)))
                    throw new InvalidOperationException("Duplicate path in latest update manifest: " + path);
            }

            var installedTag = current.StartsWith("v", StringComparison.OrdinalIgnoreCase) ? current : "v" + current;
            var expectedParent = manifest.RootElement.TryGetProperty("previous_manifest_url", out var previousUrl)
                ? previousUrl.GetString() : null;
            var processedManifests = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
            var reachedInstalledVersion = false;
            var parentCount = 0;
            while (!string.IsNullOrWhiteSpace(expectedParent) && parentCount < 10)
            {
                if (!Uri.TryCreate(expectedParent, UriKind.Absolute, out var parentUri) ||
                    parentUri.Host != "github.com" || !parentUri.AbsolutePath.StartsWith($"/{Repository}/releases/download/", StringComparison.Ordinal))
                    throw new InvalidOperationException("Previous update manifest URL is not an official DCP release URL.");
                if (!processedManifests.Add(expectedParent))
                    throw new InvalidOperationException("A cycle was found in the release delta manifest chain.");
                using var parentResponse = await updaterClient.GetAsync(expectedParent, cancellationToken);
                parentResponse.EnsureSuccessStatusCode();
                using var parentStream = await parentResponse.Content.ReadAsStreamAsync(cancellationToken);
                using var parentManifest = await JsonDocument.ParseAsync(parentStream, cancellationToken: cancellationToken);
                var parentTag = parentManifest.RootElement.GetProperty("version").GetString() ?? "";
                if (string.Equals(parentTag.TrimStart('v'), installedTag.TrimStart('v'), StringComparison.OrdinalIgnoreCase))
                {
                    reachedInstalledVersion = true;
                    break;
                }
                foreach (var parentFile in parentManifest.RootElement.GetProperty("files").EnumerateArray())
                {
                    var path = parentFile.GetProperty("path").GetString() ?? "";
                    if (string.IsNullOrWhiteSpace(path) || !IsSafeRelativePath(path))
                        throw new InvalidOperationException("Unsafe path in a previous update manifest: " + path);
                    if (!requiredFiles.ContainsKey(path))
                    {
                        var hash = parentFile.GetProperty("sha256").GetString() ?? "";
                        var size = parentFile.GetProperty("size").GetInt64();
                        requiredFiles.Add(path, (hash.ToLowerInvariant(), size));
                    }
                }
                expectedParent = parentManifest.RootElement.TryGetProperty("previous_manifest_url", out var olderUrl)
                    ? olderUrl.GetString() : null;
                parentCount++;
            }
            if (!reachedInstalledVersion)
                throw new InvalidOperationException("This installation is too far behind the available delta chain. A full package refresh is required.");

            workRoot = Path.Combine(Path.GetTempPath(), "DCP-update-" + Guid.NewGuid().ToString("N"));
            var payloadRoot = Path.Combine(workRoot, "payload");
            Directory.CreateDirectory(payloadRoot);
            var downloadRoot = $"https://raw.githubusercontent.com/{Repository}/{Uri.EscapeDataString(tag)}/";
            var safe = new List<(string Path, string Hash, long Size, string FileUrl)>();
            foreach (var file in requiredFiles)
            {
                var relative = file.Key;
                if (!IsSafeRelativePath(relative))
                    throw new InvalidOperationException("Unsafe path in update manifest: " + relative);
                if (relative.StartsWith("platform/data/", StringComparison.OrdinalIgnoreCase) ||
                    relative.StartsWith("platform/storage/", StringComparison.OrdinalIgnoreCase))
                    throw new InvalidOperationException("Update manifest attempted to include user data.");
                var url = downloadRoot + string.Join("/", relative.Split('/').Select(Uri.EscapeDataString));
                safe.Add((relative, file.Value.Hash, file.Value.Size, url));
            }

            AppendLog($"Background update: {tag} contains {safe.Count} changed files. Downloading delta payload.");
            using var semaphore = new SemaphoreSlim(4);
            var tasks = safe.Select(async file =>
            {
                await semaphore.WaitAsync(cancellationToken);
                try
                {
                    var target = Path.Combine(payloadRoot, file.Path.Replace('/', Path.DirectorySeparatorChar));
                    Directory.CreateDirectory(Path.GetDirectoryName(target)!);
                    using var res = await updaterClient.GetAsync(file.FileUrl, cancellationToken);
                    res.EnsureSuccessStatusCode();
                    await using var input = await res.Content.ReadAsStreamAsync(cancellationToken);
                    await using var output = File.Create(target);
                    await input.CopyToAsync(output, cancellationToken);
                    var info = new FileInfo(target);
                    if (info.Length != file.Size)
                        throw new InvalidOperationException("File size mismatch: " + file.Path);
                    await using var check = File.OpenRead(target);
                    var digest = await SHA256.HashDataAsync(check, cancellationToken);
                    var actual = Convert.ToHexString(digest).ToLowerInvariant();
                    if (!string.Equals(actual, file.Hash, StringComparison.OrdinalIgnoreCase))
                        throw new InvalidOperationException("SHA-256 mismatch: " + file.Path);
                }
                finally { semaphore.Release(); }
            }).ToArray();
            await Task.WhenAll(tasks);

            var manifestCopy = Path.Combine(workRoot, "update-manifest.json");
            await File.WriteAllTextAsync(manifestCopy, manifest.RootElement.GetRawText(), cancellationToken);
            var runnerSource = Path.Combine(root, "platform", "scripts", "update-windows.ps1");
            if (!File.Exists(runnerSource))
                throw new InvalidOperationException("The local delta update script is missing.");
            runnerPath = Path.Combine(Path.GetTempPath(), "DCP-Update-" + Guid.NewGuid().ToString("N") + ".ps1");
            File.Copy(runnerSource, runnerPath, true);
            var launch = new ProcessStartInfo { FileName = "powershell.exe", UseShellExecute = false, CreateNoWindow = true };
            foreach (var arg in new[] { "-NoProfile", "-ExecutionPolicy", "Bypass", "-File", runnerPath,
                "-ProcessId", Environment.ProcessId.ToString(), "-SourceRoot", workRoot, "-InstallRoot", root, "-WorkRoot", workRoot })
                launch.ArgumentList.Add(arg);
            Process.Start(launch);
            AppendLog($"Downloaded and verified the {tag} delta update. Closing to apply {safe.Count} changed files.");
            BeginInvoke(() =>
            {
                MessageBox.Show($"DCP update {tag} is ready. The application will restart to apply {safe.Count} changed files.",
                    "DCP update", MessageBoxButtons.OK, MessageBoxIcon.Information);
                Close();
            });
        }
        catch (OperationCanceledException) { }
        catch (Exception ex)
        {
            AppendLog("Background update check failed; continuing with installed version. " + ex);
            if (workRoot is not null)
            {
                try { if (Directory.Exists(workRoot)) Directory.Delete(workRoot, true); } catch { }
            }
            if (runnerPath is not null)
            {
                try { if (File.Exists(runnerPath)) File.Delete(runnerPath); } catch { }
            }
        }
    }

    private static bool IsSafeRelativePath(string path)
    {
        if (string.IsNullOrWhiteSpace(path) || Path.IsPathRooted(path) || path.Contains('\\') ||
            path.StartsWith("/", StringComparison.Ordinal) || path.Contains(':'))
            return false;
        return path.Split('/').All(part => part.Length > 0 && part != "." && part != "..");
    }

    private static bool IsNewerRelease(string latestTag, string currentVersion)
    {
        static (Version Core, string Pre) Parse(string value)
        {
            value = value.Trim().TrimStart('v');
            var match = Regex.Match(value, @"^(\d+\.\d+\.\d+)(?:-([0-9A-Za-z.-]+))?");
            if (!match.Success || !Version.TryParse(match.Groups[1].Value, out var version))
                return (new Version(0, 0, 0), "");
            return (version, match.Groups[2].Success ? match.Groups[2].Value : "");
        }

        var latest = Parse(latestTag);
        var current = Parse(currentVersion.Split('+')[0]);
        var coreCompare = latest.Core.CompareTo(current.Core);
        if (coreCompare != 0) return coreCompare > 0;
        if (latest.Pre.Length == 0) return current.Pre.Length > 0;
        if (current.Pre.Length == 0) return false;
        return ComparePrerelease(latest.Pre, current.Pre) > 0;
    }

    private static int ComparePrerelease(string left, string right)
    {
        var a = left.Split('.');
        var b = right.Split('.');
        for (var i = 0; i < Math.Min(a.Length, b.Length); i++)
        {
            var an = int.TryParse(a[i], out var av);
            var bn = int.TryParse(b[i], out var bv);
            var compare = an && bn ? av.CompareTo(bv) :
                an ? -1 : bn ? 1 : string.Compare(a[i], b[i], StringComparison.Ordinal);
            if (compare != 0) return compare;
        }
        return a.Length.CompareTo(b.Length);
    }

    private static int FindAvailablePort()
    {
        using var listener = new TcpListener(IPAddress.Loopback, 0);
        listener.Start();
        var port = ((IPEndPoint)listener.LocalEndpoint).Port;
        listener.Stop();
        return port;
    }

    private string? FindCompatiblePhp(string platform)
    {
        var candidates = new List<string>();
        var path = Environment.GetEnvironmentVariable("PATH") ?? string.Empty;
        foreach (var directory in path.Split(Path.PathSeparator, StringSplitOptions.RemoveEmptyEntries))
        {
            var candidate = Path.Combine(directory.Trim(), "php.exe");
            if (File.Exists(candidate)) candidates.Add(candidate);
        }
        candidates.Add(Path.Combine(platform, "dcp-env", "runtime", "php", "windows-x64", "php.exe"));
        candidates.Add(Path.Combine(root, "runtime", "php", "php.exe")); // backward-compatible package path

        foreach (var candidate in candidates.Distinct(StringComparer.OrdinalIgnoreCase))
        {
            if (IsCompatiblePhp(candidate))
            {
                AppendLog("Selected compatible PHP runtime: " + candidate);
                return candidate;
            }
        }
        return null;
    }

    private static bool IsCompatiblePhp(string candidate)
    {
        if (!File.Exists(candidate)) return false;
        try
        {
            var start = new ProcessStartInfo
            {
                FileName = candidate,
                UseShellExecute = false,
                CreateNoWindow = true,
                RedirectStandardOutput = true,
                RedirectStandardError = true
            };
            start.ArgumentList.Add("-r");
            start.ArgumentList.Add("echo (version_compare(PHP_VERSION,'8.2.0','>=') && extension_loaded('pdo_sqlite') && extension_loaded('curl') && extension_loaded('intl') && extension_loaded('mbstring') && extension_loaded('fileinfo') && extension_loaded('openssl') && extension_loaded('sodium')) ? 'DCP_OK' : 'DCP_MISSING';");
            using var process = Process.Start(start);
            if (process is null) return false;
            var output = process.StandardOutput.ReadToEnd();
            _ = process.StandardError.ReadToEnd();
            if (!process.WaitForExit(5000))
            {
                process.Kill(true);
                return false;
            }
            return process.ExitCode == 0 && output.Trim() == "DCP_OK";
        }
        catch { return false; }
    }

    private async Task<bool> IsServerReadyAsync()
    {
        try
        {
            using var handler = new SocketsHttpHandler
            {
                UseProxy = false,
                ConnectTimeout = TimeSpan.FromMilliseconds(500)
            };
            using var client = new HttpClient(handler) { Timeout = TimeSpan.FromMilliseconds(900) };
            using var response = await client.GetAsync($"http://127.0.0.1:{Port}/api/bootstrap");
            return response.IsSuccessStatusCode;
        }
        catch { return false; }
    }

    private static bool IsInternal(string uri)
    {
        if (!Uri.TryCreate(uri, UriKind.Absolute, out var address)) return false;
        return (address.Scheme == Uri.UriSchemeHttp || address.Scheme == Uri.UriSchemeHttps) &&
            (address.Host.Equals("127.0.0.1", StringComparison.OrdinalIgnoreCase) ||
             address.Host.Equals("localhost", StringComparison.OrdinalIgnoreCase));
    }

    private static void OpenExternal(string uri)
    {
        try { Process.Start(new ProcessStartInfo(uri) { UseShellExecute = true }); }
        catch { }
    }

    private void AppendLog(string message)
    {
        try
        {
            Directory.CreateDirectory(Path.GetDirectoryName(LogPath)!);
            lock (this)
                File.AppendAllText(LogPath, $"[{DateTimeOffset.Now:O}] {message}{Environment.NewLine}");
        }
        catch { }
    }

    private void ShowStartupError(string message)
    {
        AppendLog(message);
        MessageBox.Show(message, "DCP", MessageBoxButtons.OK, MessageBoxIcon.Error);
    }

    private void StopDcp(object? sender, FormClosingEventArgs e)
    {
        updaterCancellation?.Cancel();
        updaterClient.Dispose();
        try
        {
            if (php is { HasExited: false })
            {
                AppendLog("Stopping DCP private PHP service.");
                php.Kill(true);
            }
        }
        catch (Exception ex) { AppendLog("PHP shutdown warning: " + ex.Message); }
    }
}
