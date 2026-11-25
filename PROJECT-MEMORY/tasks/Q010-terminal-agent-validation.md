# Q010 Windows terminal and cross-platform agent status — DC34

Date: 2026-11-25
Branch: feature/local-ai-runtime-discovery

## Windows interactive terminal validation
Ran the actual TerminalService.php under the interactive Windows account AMRWIN11\amradeldotcom (session 1), using the packaged DCP PHP runtime and the installed DCP platform root.

Initial smoke found a Windows-only bug: the allow-listed pwd implementation used cmd.exe /c cd through PHP proc_open(array). Windows argument quoting caused cmd to interpret a quoted command token incorrectly. Replaced the Windows pwd implementation with PowerShell Get-Location, still using argument-array proc_open and no shell evaluation.

Passing real Windows commands:
- whoami — confirmed AMRWIN11\amradeldotcom.
- pwd — returned the installed DCP platform directory.
- ls . — listed the workspace root (28 lines).
- ls app — listed the application folder.
- date — returned a timestamp.
- php -v and php -m — returned the packaged PHP runtime and modules.

Four unsafe/out-of-scope commands were rejected: ls .., rm -rf /, curl https://example.com, git status --short. No files were changed by the terminal smoke.

## Agent adapter status validation
Ran AgentService::all() using the real plugin manifests and drivers:
- Windows interactive session: DCP Local Node reported installed/running/connected; DeepSeek Harness correctly reported macOS-only/not installed; Desktop Commander process was not detected and remains not connected.
- macOS host: DCP Local Node reported active; DeepSeek Harness process was detected running but its task adapter remains unverified; Desktop Commander Remote process was not detected and remains not connected.
- Ubuntu 24.04: DCP Local Node reported active; macOS-only DeepSeek Harness correctly reported unavailable; Desktop Commander Remote process was not detected.

Fixed platform-specific probing:
- DeepSeek Harness no longer attempts /usr/bin/pgrep or the macOS app path on Windows/Linux.
- Desktop Commander uses PowerShell Win32_Process discovery on Windows and pgrep on Unix.

Important limitation: these checks validate status discovery only. Desktop Commander and DeepSeek Harness still advertise detection-only integration; do not report them as connected or claim task execution. Their real authenticated lifecycle adapters remain open work.

## Tests
- Windows interactive TerminalService smoke: DCP_Q010_WINDOWS_TERMINAL_INTERACTIVE_PASS.
- Windows AgentService smoke: DCP_Q010_WINDOWS_AGENT_STATUS_PASS.
- macOS AgentService smoke: DCP_Q010_MAC_AGENT_STATUS_PASS.
- Ubuntu AgentService smoke: DCP_Q010_LINUX_AGENT_STATUS_PASS.
- system-tools-contract.php — ALL SYSTEM TOOLS CONTRACT CHECKS PASSED.
- Full platform suite — ALL PLATFORM TESTS PASSED.
- PHP lint and git diff --check — PASS.

Test harnesses were removed after execution. No DCP database, master.key, existing Ollama model, or unrelated service was modified.
