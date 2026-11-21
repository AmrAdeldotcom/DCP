#import <Cocoa/Cocoa.h>
#import <WebKit/WebKit.h>
#import <unistd.h>
#import <sys/socket.h>
#import <netinet/in.h>
#import <arpa/inet.h>
#import <string.h>

@interface DCPAppDelegate : NSObject <NSApplicationDelegate, WKNavigationDelegate, WKUIDelegate>
@property(nonatomic,strong) NSWindow *window;
@property(nonatomic,strong) WKWebView *webView;
@property(nonatomic,strong) NSTask *phpTask;
@property(nonatomic,assign) BOOL ownsPHP;
@property(nonatomic,assign) NSInteger port;
@property(nonatomic,strong) NSURL *platformURL;
- (void)loadAttempt:(NSInteger)attempt;
- (void)startPHPIfNeeded;
- (void)checkForUpdates;
@end

@implementation DCPAppDelegate
- (void)applicationDidFinishLaunching:(NSNotification *)note {
    NSURL *root=[[[NSBundle mainBundle] bundleURL] URLByDeletingLastPathComponent];
    self.platformURL=[root URLByAppendingPathComponent:@"platform" isDirectory:YES];
    self.port=[self availablePort];
    WKWebViewConfiguration *config=[[WKWebViewConfiguration alloc] init];
    self.webView=[[WKWebView alloc] initWithFrame:NSMakeRect(0,0,1280,820) configuration:config];
    self.webView.navigationDelegate=self;
    self.webView.UIDelegate=self;
    self.window=[[NSWindow alloc] initWithContentRect:NSMakeRect(0,0,1280,820)
        styleMask:(NSWindowStyleMaskTitled|NSWindowStyleMaskClosable|NSWindowStyleMaskMiniaturizable|NSWindowStyleMaskResizable)
        backing:NSBackingStoreBuffered defer:NO];
    self.window.title=@"DCP — Desktop Connector Platform";
    self.window.minSize=NSMakeSize(980,680);
    [self.window center];
    self.window.contentView=self.webView;
    [self.window makeKeyAndOrderFront:nil];
    [self.window orderFrontRegardless];
    [NSApp activateIgnoringOtherApps:YES];
    [self startPHPIfNeeded];
    [self loadAttempt:0];
}
// DCP owns its local interface. Any non-local navigation is handed to the
// user's default browser so external sites cannot run inside the DCP WebView.
- (BOOL)isDCPInternalURL:(NSURL *)url {
    NSString *host=url.host.lowercaseString ?: @"";
    return ([url.scheme.lowercaseString isEqualToString:@"http"] || [url.scheme.lowercaseString isEqualToString:@"https"]) &&
           ([host isEqualToString:@"127.0.0.1"] || [host isEqualToString:@"localhost"]);
}
- (void)webView:(WKWebView *)webView decidePolicyForNavigationAction:(WKNavigationAction *)action decisionHandler:(void (^)(WKNavigationActionPolicy))decisionHandler {
    NSURL *url=action.request.URL;
    if(url && [self isDCPInternalURL:url]) { decisionHandler(WKNavigationActionPolicyAllow); return; }
    decisionHandler(WKNavigationActionPolicyCancel);
    if(url && url.scheme.length) {
        dispatch_async(dispatch_get_main_queue(), ^{ [[NSWorkspace sharedWorkspace] openURL:url]; });
    }
}
- (WKWebView *)webView:(WKWebView *)webView createWebViewWithConfiguration:(WKWebViewConfiguration *)configuration forNavigationAction:(WKNavigationAction *)action windowFeatures:(WKWindowFeatures *)windowFeatures {
    NSURL *url=action.request.URL;
    if(url && [self isDCPInternalURL:url]) { [webView loadRequest:action.request]; }
    else if(url && url.scheme.length) { [[NSWorkspace sharedWorkspace] openURL:url]; }
    return nil;
}
- (NSInteger)availablePort {
    int fd=socket(AF_INET,SOCK_STREAM,0);
    if(fd<0) return 18765;
    struct sockaddr_in address; memset(&address,0,sizeof(address));
    address.sin_len=sizeof(address); address.sin_family=AF_INET;
    address.sin_addr.s_addr=htonl(INADDR_LOOPBACK); address.sin_port=0;
    NSInteger port=18765;
    if(bind(fd,(struct sockaddr *)&address,sizeof(address))==0){
        socklen_t length=sizeof(address);
        if(getsockname(fd,(struct sockaddr *)&address,&length)==0) port=ntohs(address.sin_port);
    }
    close(fd);
    return port;
}
- (void)startPHPIfNeeded {
    NSURL *runtimeRoot=[self.platformURL URLByDeletingLastPathComponent];
    NSURL *privateRuntime=[runtimeRoot URLByAppendingPathComponent:@"runtime/php/bin/php"];
    NSURL *privateRuntimeFlat=[runtimeRoot URLByAppendingPathComponent:@"runtime/php/php"];
    NSArray *phpCandidates=@[privateRuntime.path,privateRuntimeFlat.path,@"/usr/local/bin/php",@"/opt/homebrew/bin/php"];
    NSString *php=nil;
    for(NSString *candidate in phpCandidates) if([[NSFileManager defaultManager] isExecutableFileAtPath:candidate]) {php=candidate;break;}
    if(!php){[self.webView loadHTMLString:@"<h2>PHP 8.2+ is required</h2><p>Install PHP with PDO_SQLite, cURL, OpenSSL and Sodium.</p>" baseURL:nil];return;}
    NSURL *publicDir=[self.platformURL URLByAppendingPathComponent:@"public" isDirectory:YES];
    NSURL *router=[publicDir URLByAppendingPathComponent:@"router.php"];
    NSURL *logs=[self.platformURL URLByAppendingPathComponent:@"storage/logs" isDirectory:YES];
    [[NSFileManager defaultManager] createDirectoryAtURL:logs withIntermediateDirectories:YES attributes:@{NSFilePosixPermissions:@0700} error:nil];
    NSURL *log=[logs URLByAppendingPathComponent:@"desktop-wrapper.log"];
    [[NSFileManager defaultManager] createFileAtPath:log.path contents:nil attributes:@{NSFilePosixPermissions:@0600}];
    NSFileHandle *handle=[NSFileHandle fileHandleForWritingAtPath:log.path];
    self.phpTask=[[NSTask alloc] init];
    self.phpTask.executableURL=[NSURL fileURLWithPath:php];
    NSString *address=[NSString stringWithFormat:@"127.0.0.1:%ld",(long)self.port];
    self.phpTask.arguments=@[@"-S",address,@"-t",publicDir.path,router.path];
    self.phpTask.currentDirectoryURL=self.platformURL;
    self.phpTask.standardOutput=handle;
    self.phpTask.standardError=handle;
    NSError *error=nil;
    if([self.phpTask launchAndReturnError:&error]) self.ownsPHP=YES;
    else {NSString *msg=[NSString stringWithFormat:@"Unable to start private PHP service: %@",error.localizedDescription];[self.webView loadHTMLString:[NSString stringWithFormat:@"<h2>DCP could not start</h2><p>%@</p>",msg] baseURL:nil];}
}
- (void)loadAttempt:(NSInteger)attempt {
    if(attempt>=60){[self.webView loadHTMLString:@"<html><body style='font:16px -apple-system;padding:40px;background:#f3f6fa;color:#123'><h2>DCP service is not responding</h2><p>Check platform/storage/logs/desktop-wrapper.log.</p></body></html>" baseURL:nil];return;}
    NSString *statusURL=[NSString stringWithFormat:@"http://127.0.0.1:%ld/api/status",(long)self.port];
    NSURL *url=[NSURL URLWithString:statusURL];
    [[[NSURLSession sharedSession] dataTaskWithURL:url completionHandler:^(NSData *data,NSURLResponse *response,NSError *error){
        dispatch_async(dispatch_get_main_queue(), ^{
            if([(NSHTTPURLResponse *)response statusCode]==200){
                NSString *homeURL=[NSString stringWithFormat:@"http://127.0.0.1:%ld/",(long)self.port];
                [self.webView loadRequest:[NSURLRequest requestWithURL:[NSURL URLWithString:homeURL]]];
                [self.window makeKeyAndOrderFront:nil];
                [self.window orderFrontRegardless];
                [NSApp activateIgnoringOtherApps:YES];
                [self checkForUpdates];
            } else {
                dispatch_after(dispatch_time(DISPATCH_TIME_NOW,(int64_t)(0.3*NSEC_PER_SEC)),dispatch_get_main_queue(),^{[self loadAttempt:attempt+1];});
            }
        });
    }] resume];
}
- (void)checkForUpdates {
    NSURL *url=[NSURL URLWithString:@"https://api.github.com/repos/AmrAdeldotcom/DCP/releases?per_page=10"];
    NSMutableURLRequest *request=[NSMutableURLRequest requestWithURL:url cachePolicy:NSURLRequestReloadIgnoringLocalCacheData timeoutInterval:8.0];
    [request setValue:@"application/vnd.github+json" forHTTPHeaderField:@"Accept"];
    [request setValue:@"DCP-macOS-Updater/1.0" forHTTPHeaderField:@"User-Agent"];
    [[[NSURLSession sharedSession] dataTaskWithRequest:request completionHandler:^(NSData *data,NSURLResponse *response,NSError *error){
        if(error || !data || [(NSHTTPURLResponse *)response statusCode]!=200) return;
        NSError *jsonError=nil;
        id json=[NSJSONSerialization JSONObjectWithData:data options:0 error:&jsonError];
        if(jsonError || ![json isKindOfClass:[NSArray class]]) return;
        NSDictionary *release=nil;
        for(id item in (NSArray *)json){
            if(![item isKindOfClass:[NSDictionary class]] || [item[@"draft"] boolValue]) continue;
            for(NSDictionary *asset in item[@"assets"] ?: @[]){
                if([asset[@"name"] isEqualToString:@"DCP-macOS-universal.zip"]){ release=item; break; }
            }
            if(release) break;
        }
        if(!release) return;
        NSString *tag=[release[@"tag_name"] isKindOfClass:[NSString class]]?release[@"tag_name"]:@"";
        NSString *latest=[tag hasPrefix:@"v"]?[tag substringFromIndex:1]:tag;
        NSString *current=[[NSBundle mainBundle] objectForInfoDictionaryKey:@"CFBundleShortVersionString"] ?: @"0.0.0";
        if([latest compare:current options:NSNumericSearch]!=NSOrderedDescending) return;
        dispatch_async(dispatch_get_main_queue(), ^{
            NSAlert *alert=[NSAlert new];
            alert.messageText=[NSString stringWithFormat:@"DCP %@ is available",tag];
            alert.informativeText=@"A verified update package is available. DCP will close, update only its application package, preserve your local database and encryption keys, then reopen.";
            [alert addButtonWithTitle:@"Update now"];
            [alert addButtonWithTitle:@"Later"];
            if([alert runModal]!=NSAlertFirstButtonReturn) return;

            NSString *root=self.platformURL.URLByDeletingLastPathComponent.path;
            NSString *installer=[root stringByAppendingPathComponent:@"platform/Install-DCP-Online.sh"];
            if(![[NSFileManager defaultManager] isExecutableFileAtPath:installer]){
                installer=[root stringByAppendingPathComponent:@"platform/Install-DCP-Online.sh"];
                [[NSFileManager defaultManager] setAttributes:@{NSFilePosixPermissions:@0700} ofItemAtPath:installer error:nil];
            }
            if(![[NSFileManager defaultManager] fileExistsAtPath:installer]){
                NSAlert *failure=[NSAlert new]; failure.messageText=@"DCP updater is missing"; failure.informativeText=@"Run the official DCP Terminal installer to repair this installation."; [failure runModal]; return;
            }
            NSString *waitScript=@"pid=\"$1\"; installer=\"$2\"; install_root=\"$3\"; while kill -0 \"$pid\" 2>/dev/null; do sleep 1; done; DCP_INSTALL_DIR=\"$install_root\" /bin/bash \"$installer\" || open \"$install_root/DCP.app\"";
            NSTask *updater=[NSTask new]; updater.executableURL=[NSURL fileURLWithPath:@"/bin/bash"];
            updater.arguments=@[@"-c",waitScript,@"dcp-updater",[NSString stringWithFormat:@"%d",getpid()],installer,root];
            NSError *launchError=nil;
            if(![updater launchAndReturnError:&launchError]){
                NSAlert *failure=[NSAlert new]; failure.messageText=@"Could not start the DCP updater"; failure.informativeText=launchError.localizedDescription ?: @"Unknown error"; [failure runModal]; return;
            }
            [NSApp terminate:nil];
        });
    }] resume];
}
- (void)applicationWillTerminate:(NSNotification *)notification {
    if(self.ownsPHP && self.phpTask.running) [self.phpTask terminate];
}
- (BOOL)applicationShouldTerminateAfterLastWindowClosed:(NSApplication *)sender { return YES; }
@end

int main(int argc,const char *argv[]){
    @autoreleasepool {
        NSApplication *app=[NSApplication sharedApplication];
        DCPAppDelegate *delegate=[DCPAppDelegate new];
        app.delegate=delegate;
        [app setActivationPolicy:NSApplicationActivationPolicyRegular];
        [app run];
        return 0;
    }
}
