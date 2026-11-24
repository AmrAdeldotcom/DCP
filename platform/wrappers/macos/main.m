#import <Cocoa/Cocoa.h>
#import <WebKit/WebKit.h>
#import <unistd.h>
#import <sys/socket.h>
#import <netinet/in.h>
#import <arpa/inet.h>
#import <CommonCrypto/CommonDigest.h>
#import <string.h>

@interface DCPAppDelegate : NSObject <NSApplicationDelegate, WKNavigationDelegate, WKUIDelegate>
@property(nonatomic,strong) NSWindow *window;
@property(nonatomic,strong) WKWebView *webView;
@property(nonatomic,strong) NSTask *phpTask;
@property(nonatomic,strong) NSTask *updateCheckTask;
@property(nonatomic,strong) NSTask *updateApplyTask;
@property(nonatomic,strong) NSString *phpBinaryPath;
@property(nonatomic,strong) NSDictionary<NSString *,NSString *> *phpEnvironment;
@property(nonatomic,assign) BOOL ownsPHP;
@property(nonatomic,assign) NSInteger port;
@property(nonatomic,strong) NSURL *platformURL;
@property(nonatomic,strong) NSURL *userDataURL;
- (void)loadAttempt:(NSInteger)attempt;
- (void)startPHPIfNeeded;
- (void)checkForUpdates;
@end

@implementation DCPAppDelegate
- (void)applicationDidFinishLaunching:(NSNotification *)note {
    NSURL *root=[[[NSBundle mainBundle] bundleURL] URLByDeletingLastPathComponent];
    self.platformURL=[root URLByAppendingPathComponent:@"platform" isDirectory:YES];
    self.userDataURL=[root URLByAppendingPathComponent:@"DCP-UserData/macOS" isDirectory:YES];
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
- (BOOL)isCompatiblePHP:(NSString *)path {
    NSTask *task=[[NSTask alloc] init];
    task.executableURL=[NSURL fileURLWithPath:path];
    task.arguments=@[@"-r", @"echo (version_compare(PHP_VERSION,'8.2.0','>=') && extension_loaded('pdo_sqlite') && extension_loaded('curl') && extension_loaded('intl') && extension_loaded('mbstring') && extension_loaded('fileinfo') && extension_loaded('openssl') && extension_loaded('sodium') && class_exists('PharData') && function_exists('posix_kill')) ? 'DCP_OK' : 'DCP_MISSING';"];
    NSPipe *pipe=[NSPipe pipe];task.standardOutput=pipe;task.standardError=[NSFileHandle fileHandleWithNullDevice];
    NSError *error=nil;
    if(![task launchAndReturnError:&error])return NO;
    NSData *data=[pipe.fileHandleForReading readDataToEndOfFile];
    [task waitUntilExit];
    NSString *output=[[NSString alloc] initWithData:data encoding:NSUTF8StringEncoding] ?: @"";
    return task.terminationStatus==0 && [output containsString:@"DCP_OK"];
}
- (BOOL)isVerifiedManagedPHP:(NSString *)path {
    NSURL *binaryURL=[NSURL fileURLWithPath:path];
    NSURL *parent=[binaryURL URLByDeletingLastPathComponent];
    NSURL *runtimeURL=[parent.lastPathComponent isEqualToString:@"bin"] ? [parent URLByDeletingLastPathComponent] : parent;
    NSString *runtimeRoot=runtimeURL.path;
    NSString *checksumPath=[runtimeRoot stringByAppendingPathComponent:@"php.sha256"];
    NSString *expected=[NSString stringWithContentsOfFile:checksumPath encoding:NSUTF8StringEncoding error:nil];
    expected=[[expected stringByTrimmingCharactersInSet:[NSCharacterSet whitespaceAndNewlineCharacterSet]] lowercaseString];
    if(expected.length!=CC_SHA256_DIGEST_LENGTH*2) return NO;
    NSData *binary=[NSData dataWithContentsOfFile:path options:NSDataReadingMappedIfSafe error:nil];
    if(!binary) return NO;
    unsigned char digest[CC_SHA256_DIGEST_LENGTH];
    CC_SHA256(binary.bytes,(CC_LONG)binary.length,digest);
    NSMutableString *actual=[NSMutableString stringWithCapacity:CC_SHA256_DIGEST_LENGTH*2];
    for(NSUInteger i=0;i<CC_SHA256_DIGEST_LENGTH;i++) [actual appendFormat:@"%02x",digest[i]];
    return [actual isEqualToString:expected];
}
- (void)startPHPIfNeeded {
#if defined(__arm64__)
    NSString *managedName=@"macos-arm64";
#else
    NSString *managedName=@"macos-x64";
#endif
    NSURL *managed=[self.platformURL URLByAppendingPathComponent:[NSString stringWithFormat:@"dcp-env/runtime/php/%@/bin/php",managedName]];
    NSURL *managedFlat=[self.platformURL URLByAppendingPathComponent:[NSString stringWithFormat:@"dcp-env/runtime/php/%@/php",managedName]];
    NSURL *legacy=[[[self.platformURL URLByDeletingLastPathComponent] URLByAppendingPathComponent:@"runtime/php/bin/php"] copy];
    NSArray *phpCandidates=@[managed.path,managedFlat.path,@"/opt/homebrew/bin/php",@"/usr/local/bin/php",legacy.path];
    NSString *php=nil;
    BOOL managedPHP=NO;
    for(NSString *candidate in phpCandidates){
        BOOL candidateIsManaged=[candidate isEqualToString:managed.path] || [candidate isEqualToString:managedFlat.path];
        if(candidateIsManaged && ![self isVerifiedManagedPHP:candidate]) continue;
        if([[NSFileManager defaultManager] isExecutableFileAtPath:candidate] && [self isCompatiblePHP:candidate]){
            php=candidate;
            managedPHP=candidateIsManaged;
            break;
        }
    }
    if(!php){[self.webView loadHTMLString:@"<h2>Compatible PHP 8.2+ is required</h2><p>Run platform/dcp-env/scripts/bootstrap-macos.sh. DCP will not replace an existing PHP installation.</p>" baseURL:nil];return;}
    self.phpBinaryPath=php;
    NSURL *publicDir=[self.platformURL URLByAppendingPathComponent:@"public" isDirectory:YES];
    NSURL *router=[publicDir URLByAppendingPathComponent:@"router.php"];
    NSURL *dataDir=[self.userDataURL URLByAppendingPathComponent:@"data" isDirectory:YES];
    NSURL *storageDir=[self.userDataURL URLByAppendingPathComponent:@"storage" isDirectory:YES];
    [[NSFileManager defaultManager] createDirectoryAtURL:dataDir withIntermediateDirectories:YES attributes:@{NSFilePosixPermissions:@0700} error:nil];
    [[NSFileManager defaultManager] createDirectoryAtURL:storageDir withIntermediateDirectories:YES attributes:@{NSFilePosixPermissions:@0700} error:nil];
    NSURL *logs=[storageDir URLByAppendingPathComponent:@"logs" isDirectory:YES];
    [[NSFileManager defaultManager] createDirectoryAtURL:logs withIntermediateDirectories:YES attributes:@{NSFilePosixPermissions:@0700} error:nil];
    NSURL *log=[logs URLByAppendingPathComponent:@"desktop-wrapper.log"];
    [[NSFileManager defaultManager] createFileAtPath:log.path contents:nil attributes:@{NSFilePosixPermissions:@0600}];
    NSFileHandle *handle=[NSFileHandle fileHandleForWritingAtPath:log.path];
    self.phpTask=[[NSTask alloc] init];
    self.phpTask.executableURL=[NSURL fileURLWithPath:php];
    NSString *address=[NSString stringWithFormat:@"127.0.0.1:%ld",(long)self.port];
    self.phpTask.arguments=@[@"-S",address,@"-t",publicDir.path,router.path];
    self.phpTask.currentDirectoryURL=self.platformURL;
    NSMutableDictionary *environment=[NSMutableDictionary dictionaryWithDictionary:[[NSProcessInfo processInfo] environment]];
    environment[@"DCP_MODE"]=@"desktop";
    environment[@"DCP_USER_DATA_DIR"]=self.userDataURL.path;
    if(managedPHP){
        NSURL *runtimeRoot=[[managed URLByDeletingLastPathComponent] URLByDeletingLastPathComponent];
        environment[@"PHPRC"]=[[runtimeRoot URLByAppendingPathComponent:@"php.ini"] path];
        environment[@"PHP_INI_SCAN_DIR"]=[[runtimeRoot URLByAppendingPathComponent:@"conf.d" isDirectory:YES] path];
    }
    self.phpTask.environment=environment;
    self.phpEnvironment=[environment copy];
    NSString *runtimePortPath=[storageDir.path stringByAppendingPathComponent:@"runtime-port.txt"];
    NSString *runtimePidPath=[storageDir.path stringByAppendingPathComponent:@"runtime-pid.txt"];
    [[NSString stringWithFormat:@"%ld\n",(long)self.port] writeToFile:runtimePortPath atomically:YES encoding:NSUTF8StringEncoding error:nil];
    [[NSString stringWithFormat:@"%d\n",getpid()] writeToFile:runtimePidPath atomically:YES encoding:NSUTF8StringEncoding error:nil];
    [[NSFileManager defaultManager] setAttributes:@{NSFilePosixPermissions:@0600} ofItemAtPath:runtimePortPath error:nil];
    [[NSFileManager defaultManager] setAttributes:@{NSFilePosixPermissions:@0600} ofItemAtPath:runtimePidPath error:nil];
    self.phpTask.standardOutput=handle;
    self.phpTask.standardError=handle;
    NSError *error=nil;
    if([self.phpTask launchAndReturnError:&error]) self.ownsPHP=YES;
    else {NSString *msg=[NSString stringWithFormat:@"Unable to start PHP service: %@",error.localizedDescription];[self.webView loadHTMLString:[NSString stringWithFormat:@"<h2>DCP could not start</h2><p>%@</p>",msg] baseURL:nil];}
}
- (void)loadAttempt:(NSInteger)attempt {
    if(attempt>=60){[self.webView loadHTMLString:@"<html><body style='font:16px -apple-system;padding:40px;background:#f3f6fa;color:#123'><h2>DCP service is not responding</h2><p>Check DCP-UserData/macOS/storage/logs/desktop-wrapper.log.</p></body></html>" baseURL:nil];return;}
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
    if(self.phpBinaryPath.length==0) return;
    NSString *root=self.platformURL.URLByDeletingLastPathComponent.path;
    NSString *script=[self.platformURL URLByAppendingPathComponent:@"scripts/update-macos.php"].path;
    if(![[NSFileManager defaultManager] isReadableFileAtPath:script]) return;
    NSString *version=[[NSBundle mainBundle] objectForInfoDictionaryKey:@"CFBundleShortVersionString"] ?: @"0.0.0";
    NSInteger revision=[[[NSBundle mainBundle] objectForInfoDictionaryKey:@"DCPPackageRevision"] respondsToSelector:@selector(integerValue)]
        ? [[[NSBundle mainBundle] objectForInfoDictionaryKey:@"DCPPackageRevision"] integerValue] : 0;
    dispatch_async(dispatch_get_global_queue(QOS_CLASS_UTILITY,0), ^{
        NSTask *check=[NSTask new];
        self.updateCheckTask=check;
        check.executableURL=[NSURL fileURLWithPath:self.phpBinaryPath];
        check.arguments=@[script,@"--check",@"--root",root,@"--version",version,@"--revision",[NSString stringWithFormat:@"%ld",(long)revision]];
        check.environment=self.phpEnvironment;
        NSPipe *pipe=[NSPipe pipe];
        check.standardOutput=pipe;
        check.standardError=[NSFileHandle fileHandleWithNullDevice];
        NSError *checkError=nil;
        if(![check launchAndReturnError:&checkError]) {
            dispatch_async(dispatch_get_main_queue(), ^{ if(self.updateCheckTask==check) self.updateCheckTask=nil; });
            return;
        }
        NSData *output=[pipe.fileHandleForReading readDataToEndOfFile];
        [check waitUntilExit];
        dispatch_async(dispatch_get_main_queue(), ^{ if(self.updateCheckTask==check) self.updateCheckTask=nil; });
        if(check.terminationStatus!=0 || output.length==0) return;
        NSError *jsonError=nil;
        id response=[NSJSONSerialization JSONObjectWithData:output options:0 error:&jsonError];
        if(jsonError || ![response isKindOfClass:[NSDictionary class]] || ![response[@"available"] boolValue]) return;
        NSDictionary *update=(NSDictionary *)response;
        dispatch_async(dispatch_get_main_queue(), ^{
            if(!self.window || !self.window.isVisible) return;
            NSString *latest=[update[@"version"] isKindOfClass:[NSString class]]?update[@"version"]:@"new version";
            NSString *strategy=[update[@"strategy"] isKindOfClass:[NSString class]]?update[@"strategy"]:@"full";
            NSAlert *alert=[NSAlert new];
            alert.messageText=[NSString stringWithFormat:@"DCP %@ is available",latest];
            alert.informativeText=[NSString stringWithFormat:@"A cryptographically verified %@ update is available. DCP will close, update its application package, preserve your local database and encryption keys, verify the local API, then reopen.",strategy];
            [alert addButtonWithTitle:@"Update now"];
            [alert addButtonWithTitle:@"Later"];
            if([alert runModal]!=NSAlertFirstButtonReturn) return;

            NSTask *updater=[NSTask new];
            self.updateApplyTask=updater;
            updater.executableURL=[NSURL fileURLWithPath:@"/usr/bin/nohup"];
            updater.arguments=@[
                self.phpBinaryPath,script,@"--apply",@"--root",root,
                @"--version",version,@"--revision",[NSString stringWithFormat:@"%ld",(long)revision],
                @"--pid",[NSString stringWithFormat:@"%d",getpid()]
            ];
            updater.environment=self.phpEnvironment;
            updater.standardOutput=[NSFileHandle fileHandleWithNullDevice];
            updater.standardError=[NSFileHandle fileHandleWithNullDevice];
            NSError *launchError=nil;
            if(![updater launchAndReturnError:&launchError]){
                NSAlert *failure=[NSAlert new];
                failure.messageText=@"Could not start the DCP updater";
                failure.informativeText=launchError.localizedDescription ?: @"Unknown error";
                [failure runModal];
                return;
            }
            [NSApp terminate:nil];
        });
    });
}
- (void)applicationWillTerminate:(NSNotification *)notification {
    if(self.updateCheckTask && self.updateCheckTask.running) {
        [self.updateCheckTask terminate];
        [self.updateCheckTask waitUntilExit];
    }
    NSString *storage=self.userDataURL.path;
    NSString *portFile=[storage stringByAppendingPathComponent:@"storage/runtime-port.txt"];
    NSString *pidFile=[storage stringByAppendingPathComponent:@"storage/runtime-pid.txt"];
    [[NSFileManager defaultManager] removeItemAtPath:portFile error:nil];
    [[NSFileManager defaultManager] removeItemAtPath:pidFile error:nil];
    if(self.ownsPHP && self.phpTask.running) {
        [self.phpTask terminate];
        [self.phpTask waitUntilExit];
    }
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
