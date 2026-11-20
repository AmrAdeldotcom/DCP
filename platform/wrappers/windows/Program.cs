using System.Diagnostics;
using Microsoft.Web.WebView2.WinForms;
using Microsoft.Web.WebView2.Core;
using System.Net.Http;
using System;

namespace DCP;
internal static class Program {
 [STAThread] static void Main(){ApplicationConfiguration.Initialize();Application.Run(new MainWindow());}
}
internal sealed class MainWindow : Form {
 private readonly WebView2 view=new(){Dock=DockStyle.Fill}; private Process? php; private readonly string root=AppContext.BaseDirectory; private const int Port=18765;
 public MainWindow(){Text="DCP — Desktop Connector Platform";Width=1280;Height=840;MinimumSize=new Size(980,680);Controls.Add(view);Load+=StartDcp;FormClosing+=StopDcp;}
 private async void StartDcp(object? s,EventArgs e){
  var platform=Path.Combine(root,"platform");var publicDir=Path.Combine(platform,"public");var phpPath=Path.Combine(root,"runtime","php","php.exe");
  if(!File.Exists(phpPath))phpPath="php";
  try{using var client=new HttpClient{Timeout=TimeSpan.FromSeconds(1)};await client.GetAsync($"http://127.0.0.1:{Port}/api/status");}
  catch{var p=new ProcessStartInfo(phpPath,$"-S 127.0.0.1:{Port} -t \"{publicDir}\" \"{Path.Combine(publicDir,"router.php")}\""){WorkingDirectory=platform,UseShellExecute=false,CreateNoWindow=true,RedirectStandardOutput=true,RedirectStandardError=true};var child=Process.Start(p);if(child!=null){this.php=child;_ = Task.Run(async()=>{var log=Path.Combine(platform,"storage","logs","desktop-wrapper.log");Directory.CreateDirectory(Path.GetDirectoryName(log)!);await File.AppendAllTextAsync(log,await child.StandardOutput.ReadToEndAsync());});}}
  for(int i=0;i<60;i++){try{using var c=new HttpClient{Timeout=TimeSpan.FromSeconds(1)};await c.GetAsync($"http://127.0.0.1:{Port}/api/status");await view.EnsureCoreWebView2Async();view.CoreWebView2.Settings.AreDefaultContextMenusEnabled=true;
  view.CoreWebView2.NavigationStarting+=(sender,args)=>{if(!IsInternal(args.Uri)){args.Cancel=true;try{Process.Start(new ProcessStartInfo(args.Uri){UseShellExecute=true});}catch{}}};
  view.CoreWebView2.NewWindowRequested+=(sender,args)=>{args.Handled=true;try{if(!IsInternal(args.Uri))Process.Start(new ProcessStartInfo(args.Uri){UseShellExecute=true});}catch{}};
  view.CoreWebView2.Navigate($"http://127.0.0.1:{Port}/");return;}catch{await Task.Delay(250);}}
  MessageBox.Show("DCP PHP service did not start. Check platform/storage/logs.","DCP",MessageBoxButtons.OK,MessageBoxIcon.Error);
 }
 private static bool IsInternal(string uri){if(!Uri.TryCreate(uri,UriKind.Absolute,out var u))return false;return (u.Scheme==Uri.UriSchemeHttp||u.Scheme==Uri.UriSchemeHttps)&&(u.Host.Equals("127.0.0.1",StringComparison.OrdinalIgnoreCase)||u.Host.Equals("localhost",StringComparison.OrdinalIgnoreCase));}
 private void StopDcp(object? s,FormClosingEventArgs e){try{if(php is {HasExited:false})php.Kill(true);}catch{}}
}
