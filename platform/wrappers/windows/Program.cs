using System.Diagnostics;
using System.Net;
using System.Net.Sockets;
using System.Reflection;
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
    private readonly HttpClient updaterClient = new() { Timeout = TimeSpan.FromMinutes(20) };
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
        try
        {
            File.WriteAllText(Path.Combine(userData, "storage", "runtime-port.txt"), Port.ToString());
        }
        catch (Exception ex) { AppendLog("Could not persist local runtime port: " + ex.Message); }
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
                    _ = MonitorUpdatesInBackgroundAsync(updaterCancellation.Token);
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

    private async Task MonitorUpdatesInBackgroundAsync(CancellationToken cancellationToken)
    {
        using var timer = new PeriodicTimer(TimeSpan.FromMinutes(5));
        try
        {
            do
            {
                await CheckAndApplyUpdateInBackgroundAsync(cancellationToken);
                if (cancellationToken.IsCancellationRequested) break;
            } while (await timer.WaitForNextTickAsync(cancellationToken));
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
            AppendLog("Background update monitoring stopped.");
        }
    }

    private async Task CheckAndApplyUpdateInBackgroundAsync(CancellationToken cancellationToken)
    {
        string? workRoot = null;
        string? runnerPath = null;
        var updaterLaunched = false;
        try
        {
            var metadata = Assembly.GetExecutingAssembly().GetCustomAttributes<AssemblyMetadataAttribute>();
            var currentVersion = metadata.FirstOrDefault(x => string.Equals(x.Key, "DCPVersion", StringComparison.OrdinalIgnoreCase))?.Value ?? "0.0.0";
            var currentRevision = int.TryParse(metadata.FirstOrDefault(x => string.Equals(x.Key, "DCPPackageRevision", StringComparison.OrdinalIgnoreCase))?.Value, out var revision)
                ? revision : 0;

            var preparation = await WindowsUpdateClient.PrepareAsync(
                root, currentVersion, currentRevision, updaterClient, cancellationToken);
            if (preparation is null)
            {
                AppendLog($"Signed release check: installed {currentVersion} revision {currentRevision}; no newer complete synchronized release is available.");
                return;
            }

            workRoot = preparation.WorkRoot;
            AppendLog($"Verified synchronized signed release {preparation.ReleaseTag}; selected {preparation.Strategy} package with {preparation.FileCount} files.");
            var answer = MessageBox.Show(
                this,
                $"DCP {preparation.Version} is available ({preparation.Strategy} update). Install it now and restart DCP?",
                "DCP update available",
                MessageBoxButtons.YesNo,
                MessageBoxIcon.Question);
            if (answer != DialogResult.Yes)
            {
                AppendLog("User deferred the signed DCP update; the verified package remains staged at " + workRoot);
                workRoot = null;
                updaterCancellation?.Cancel();
                return;
            }

            var runnerSource = Path.Combine(root, "platform", "scripts", "update-windows.ps1");
            if (!File.Exists(runnerSource))
                throw new InvalidOperationException("The local transactional Windows updater is missing.");
            runnerPath = Path.Combine(Path.GetTempPath(), "DCP-Update-" + Guid.NewGuid().ToString("N") + ".ps1");
            File.Copy(runnerSource, runnerPath, true);

            var launch = new ProcessStartInfo
            {
                FileName = "powershell.exe",
                UseShellExecute = false,
                CreateNoWindow = true
            };
            foreach (var argument in new[]
            {
                "-NoProfile", "-ExecutionPolicy", "Bypass", "-File", runnerPath,
                "-ProcessId", Environment.ProcessId.ToString(),
                "-SourceRoot", workRoot,
                "-InstallRoot", root,
                "-WorkRoot", workRoot
            })
                launch.ArgumentList.Add(argument);

            using var updaterProcess = Process.Start(launch)
                ?? throw new InvalidOperationException("Windows could not start the transactional DCP updater.");
            updaterLaunched = true;
            workRoot = null;
            runnerPath = null;
            AppendLog($"Launched signed {preparation.Strategy} update {preparation.ReleaseTag}; closing the old process for transactional apply.");
            BeginInvoke(() =>
            {
                MessageBox.Show(
                    this,
                    $"DCP will restart to install {preparation.ReleaseTag}. Your database, encryption key and user files are protected.",
                    "DCP update",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Information);
                Close();
            });
        }
        catch (OperationCanceledException) { }
        catch (Exception ex)
        {
            AppendLog("Signed background update check/apply preparation failed; continuing with installed version. " + ex);
            if (!updaterLaunched && workRoot is not null)
            {
                try { if (Directory.Exists(workRoot)) Directory.Delete(workRoot, true); } catch { }
            }
            if (!updaterLaunched && runnerPath is not null)
            {
                try { if (File.Exists(runnerPath)) File.Delete(runnerPath); } catch { }
            }
        }
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
        try { File.Delete(Path.Combine(root, "DCP-UserData", "Windows", "storage", "runtime-port.txt")); } catch { }
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
