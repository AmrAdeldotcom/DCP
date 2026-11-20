#import <Cocoa/Cocoa.h>
#import <WebKit/WebKit.h>

@interface DCPAppDelegate : NSObject <NSApplicationDelegate, WKNavigationDelegate, WKUIDelegate>
@property(nonatomic,strong) NSWindow *window;
@property(nonatomic,strong) WKWebView *webView;
@property(nonatomic,strong) NSTask *phpTask;
@property(nonatomic,assign) BOOL ownsPHP;
@property(nonatomic,strong) NSURL *platformURL;
- (void)loadAttempt:(NSInteger)attempt;
- (void)startPHPIfNeeded;
@end

@implementation DCPAppDelegate
- (void)applicationDidFinishLaunching:(NSNotification *)note {
    NSURL *root=[[[NSBundle mainBundle] bundleURL] URLByDeletingLastPathComponent];
    self.platformURL=[root URLByAppendingPathComponent:@"platform" isDirectory:YES];
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
- (BOOL)isPHPReady {
    NSURL *url=[NSURL URLWithString:@"http://127.0.0.1:18765/api/status"];
    dispatch_semaphore_t sem=dispatch_semaphore_create(0);
    __block BOOL ok=NO;
    [[[NSURLSession sharedSession] dataTaskWithURL:url completionHandler:^(NSData *data,NSURLResponse *response,NSError *error){
        ok=[(NSHTTPURLResponse *)response statusCode]==200;
        dispatch_semaphore_signal(sem);
    }] resume];
    dispatch_semaphore_wait(sem,dispatch_time(DISPATCH_TIME_NOW,(int64_t)(1.2*NSEC_PER_SEC)));
    return ok;
}
- (void)startPHPIfNeeded {
    if([self isPHPReady]) return;
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
    self.phpTask.arguments=@[@"-S",@"127.0.0.1:18765",@"-t",publicDir.path,router.path];
    self.phpTask.currentDirectoryURL=self.platformURL;
    self.phpTask.standardOutput=handle;
    self.phpTask.standardError=handle;
    NSError *error=nil;
    if([self.phpTask launchAndReturnError:&error]) self.ownsPHP=YES;
    else {NSString *msg=[NSString stringWithFormat:@"Unable to start private PHP service: %@",error.localizedDescription];[self.webView loadHTMLString:[NSString stringWithFormat:@"<h2>DCP could not start</h2><p>%@</p>",msg] baseURL:nil];}
}
- (void)loadAttempt:(NSInteger)attempt {
    if(attempt>=60){[self.webView loadHTMLString:@"<html><body style='font:16px -apple-system;padding:40px;background:#f3f6fa;color:#123'><h2>DCP service is not responding</h2><p>Check platform/storage/logs/desktop-wrapper.log.</p></body></html>" baseURL:nil];return;}
    NSURL *url=[NSURL URLWithString:@"http://127.0.0.1:18765/api/status"];
    [[[NSURLSession sharedSession] dataTaskWithURL:url completionHandler:^(NSData *data,NSURLResponse *response,NSError *error){
        dispatch_async(dispatch_get_main_queue(), ^{
            if([(NSHTTPURLResponse *)response statusCode]==200){
                [self.webView loadRequest:[NSURLRequest requestWithURL:[NSURL URLWithString:@"http://127.0.0.1:18765/"]]];
                [self.window makeKeyAndOrderFront:nil];
                [self.window orderFrontRegardless];
                [NSApp activateIgnoringOtherApps:YES];
            } else {
                dispatch_after(dispatch_time(DISPATCH_TIME_NOW,(int64_t)(0.3*NSEC_PER_SEC)),dispatch_get_main_queue(),^{[self loadAttempt:attempt+1];});
            }
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
