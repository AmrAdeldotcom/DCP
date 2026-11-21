using System.Diagnostics;
using System.IO.Compression;
using System.Reflection;
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
    private const int Port = 18765;
    private const string Repository = "AmrAdeldotcom/DCP";
    private const string ReleaseAsset = "DCP-Windows-x64.zip";
    private readonly WebView2 view = new() { Dock = DockStyle.Fill };
    private readonly string root = AppContext.BaseDirectory.TrimEnd(Path.DirectorySeparatorChar);
    private Process? php;
    private string LogPath => Path.Combine(root, "platform", "storage", "logs", "desktop-wrapper.log");

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

        // Installed builds update from the official GitHub Release before PHP starts.
        // Source checkouts continue to use GitUpdateService inside the PHP application.
        if (await CheckAndStageUpdateAsync())
            return;

        var platform = Path.Combine(root, "platform");
        var publicDir = Path.Combine(platform, "public");
        var router = Path.Combine(publicDir, "router.php");
        var phpPath = Path.Combine(root, "runtime", "php", "php.exe");

        if (!Directory.Exists(publicDir) || !File.Exists(router))
        {
            ShowStartupError("The DCP platform files are missing. Reinstall DCP from the official GitHub release.");
            return;
        }

        if (!await IsServerReadyAsync())
        {
            if (!File.Exists(phpPath))
            {
                ShowStartupError("DCP's private PHP runtime is missing. Run the official DCP installer again; DCP will not silently use a different system PHP installation.");
                return;
            }

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
                ShowStartupError("DCP could not start its private PHP service. Details are in platform/storage/logs/desktop-wrapper.log.");
                return;
            }
        }

        for (var attempt = 0; attempt < 60; attempt++)
        {
            if (await IsServerReadyAsync())
            {
                try
                {
                    var userData = Path.Combine(
                        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                        "DCP-UserData", "WebView2");
                    Directory.CreateDirectory(userData);
                    var environment = await CoreWebView2Environment.CreateAsync(null, userData);
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
            await Task.Delay(300);
        }

        ShowStartupError("DCP PHP service did not become ready. Check platform/storage/logs/desktop-wrapper.log.");
    }

    private async Task<bool> CheckAndStageUpdateAsync()
    {
        string? workRoot = null;
        string? runnerPath = null;
        try
        {
            using var apiClient = new HttpClient { Timeout = TimeSpan.FromSeconds(10) };
            apiClient.DefaultRequestHeaders.UserAgent.ParseAdd("DCP-Desktop-Updater/1.0");
            apiClient.DefaultRequestHeaders.Accept.ParseAdd("application/vnd.github+json");

            using var response = await apiClient.GetAsync($"https://api.github.com/repos/{Repository}/releases/latest");
            response.EnsureSuccessStatusCode();
            using var release = JsonDocument.Parse(await response.Content.ReadAsStringAsync());
            var tag = release.RootElement.GetProperty("tag_name").GetString() ?? "";
            var current = Assembly.GetExecutingAssembly()
                .GetCustomAttribute<AssemblyInformationalVersionAttribute>()?.InformationalVersion ?? "0.0.0";

            if (!IsNewerRelease(tag, current))
            {
                AppendLog($"Release check: installed {current}; latest {tag}; no update required.");
                return false;
            }

            var asset = release.RootElement.GetProperty("assets").EnumerateArray()
                .FirstOrDefault(x => string.Equals(x.GetProperty("name").GetString(), ReleaseAsset, StringComparison.OrdinalIgnoreCase));
            if (asset.ValueKind == JsonValueKind.Undefined)
                throw new InvalidOperationException($"GitHub release {tag} does not contain {ReleaseAsset}.");

            var downloadUrl = asset.GetProperty("browser_download_url").GetString();
            if (string.IsNullOrWhiteSpace(downloadUrl))
                throw new InvalidOperationException("The latest Windows release has no download URL.");

            workRoot = Path.Combine(Path.GetTempPath(), "DCP-update-" + Guid.NewGuid().ToString("N"));
            var extractRoot = Path.Combine(workRoot, "extract");
            Directory.CreateDirectory(extractRoot);
            var archive = Path.Combine(workRoot, ReleaseAsset);
            using var downloadClient = new HttpClient { Timeout = TimeSpan.FromMinutes(10) };
            downloadClient.DefaultRequestHeaders.UserAgent.ParseAdd("DCP-Desktop-Updater/1.0");
            await using (var input = await downloadClient.GetStreamAsync(downloadUrl))
            await using (var output = File.Create(archive))
                await input.CopyToAsync(output);

            ZipFile.ExtractToDirectory(archive, extractRoot);
            var sourceRoot = Path.Combine(extractRoot, "windows");
            var sourceApp = Path.Combine(sourceRoot, "DCP.exe");
            var updater = Path.Combine(sourceRoot, "platform", "scripts", "update-windows.ps1");
            if (!File.Exists(sourceApp) || !File.Exists(updater))
                throw new InvalidOperationException("The downloaded release is incomplete; DCP.exe or update-windows.ps1 is missing.");

            runnerPath = Path.Combine(Path.GetTempPath(), "DCP-Update-" + Guid.NewGuid().ToString("N") + ".ps1");
            File.Copy(updater, runnerPath, true);

            var launch = new ProcessStartInfo
            {
                FileName = "powershell.exe",
                UseShellExecute = false,
                CreateNoWindow = true
            };
            launch.ArgumentList.Add("-NoProfile");
            launch.ArgumentList.Add("-ExecutionPolicy");
            launch.ArgumentList.Add("Bypass");
            launch.ArgumentList.Add("-File");
            launch.ArgumentList.Add(runnerPath);
            launch.ArgumentList.Add("-ProcessId");
            launch.ArgumentList.Add(Environment.ProcessId.ToString());
            launch.ArgumentList.Add("-SourceRoot");
            launch.ArgumentList.Add(sourceRoot);
            launch.ArgumentList.Add("-InstallRoot");
            launch.ArgumentList.Add(root);
            launch.ArgumentList.Add("-WorkRoot");
            launch.ArgumentList.Add(workRoot);

            Process.Start(launch);
            MessageBox.Show(
                $"A new official DCP release ({tag}) is ready. DCP will close, update safely, preserve your local data and reopen.",
                "DCP update",
                MessageBoxButtons.OK,
                MessageBoxIcon.Information);
            Application.Exit();
            return true;
        }
        catch (Exception ex)
        {
            AppendLog("Automatic GitHub update check/stage failed; continuing with installed version. " + ex);
            if (workRoot is not null)
            {
                try { if (Directory.Exists(workRoot)) Directory.Delete(workRoot, true); } catch { }
            }
            if (runnerPath is not null)
            {
                try { if (File.Exists(runnerPath)) File.Delete(runnerPath); } catch { }
            }
            return false;
        }
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

    private static async Task<bool> IsServerReadyAsync()
    {
        try
        {
            using var client = new HttpClient { Timeout = TimeSpan.FromSeconds(1) };
            using var response = await client.GetAsync($"http://127.0.0.1:{Port}/api/status");
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
