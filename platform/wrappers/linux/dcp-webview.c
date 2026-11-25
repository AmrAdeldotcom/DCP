#include <gtk/gtk.h>
#include <webkit2/webkit2.h>
#include <glib/gstdio.h>
#include <signal.h>
#include <unistd.h>
#include <sys/wait.h>
#include <stdio.h>
#include <limits.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <stdlib.h>
#include <string.h>
static GPid php_pid=0;
static guint dcp_port=0;
#define DCP_LOCAL_PORT 60776
static gboolean dcp_port_available(void){
 int fd=socket(AF_INET,SOCK_STREAM,0);if(fd<0)return FALSE;
 struct sockaddr_in address;memset(&address,0,sizeof(address));address.sin_family=AF_INET;
 address.sin_addr.s_addr=htonl(INADDR_LOOPBACK);address.sin_port=htons(DCP_LOCAL_PORT);
 gboolean ok=bind(fd,(struct sockaddr*)&address,sizeof(address))==0;
 close(fd);return ok;
}
static gboolean ready(gpointer data){
 WebKitWebView *view=WEBKIT_WEB_VIEW(data);GError *error=NULL;
 GSocketClient *client=g_socket_client_new();
 if(php_pid>0){int status=0;pid_t exited=waitpid(php_pid,&status,WNOHANG);if(exited==php_pid){php_pid=0;g_spawn_close_pid(exited);GtkWidget *dialog=gtk_message_dialog_new(NULL,GTK_DIALOG_MODAL,GTK_MESSAGE_ERROR,GTK_BUTTONS_CLOSE,"DCP local PHP service exited during startup. Check the DCP application logs.");gtk_dialog_run(GTK_DIALOG(dialog));gtk_widget_destroy(dialog);gtk_main_quit();return G_SOURCE_REMOVE;}}
 GSocketConnection *connection=g_socket_client_connect_to_host(client,"127.0.0.1",dcp_port,NULL,&error);
 if(connection){
  g_object_unref(connection);g_object_unref(client);
  gchar *url=g_strdup_printf("http://127.0.0.1:%u/",dcp_port);webkit_web_view_load_uri(view,url);g_free(url);
  return G_SOURCE_REMOVE;
 }
 g_clear_error(&error);g_object_unref(client);return G_SOURCE_CONTINUE;
}
static gboolean is_internal_uri(const gchar *uri){GUri *u=g_uri_parse(uri,G_URI_FLAGS_NONE,NULL);if(!u)return FALSE;const gchar *scheme=g_uri_get_scheme(u),*host=g_uri_get_host(u);gboolean ok=scheme&&host&&(!g_ascii_strcasecmp(scheme,"http")||!g_ascii_strcasecmp(scheme,"https"))&&(!g_ascii_strcasecmp(host,"127.0.0.1")||!g_ascii_strcasecmp(host,"localhost"));g_uri_unref(u);return ok;}
static gboolean navigation_policy(WebKitWebView *view,WebKitPolicyDecision *decision,WebKitPolicyDecisionType type,gpointer data){if(type!=WEBKIT_POLICY_DECISION_TYPE_NAVIGATION_ACTION&&type!=WEBKIT_POLICY_DECISION_TYPE_NEW_WINDOW_ACTION)return FALSE;WebKitNavigationPolicyDecision *nav=WEBKIT_NAVIGATION_POLICY_DECISION(decision);WebKitURIRequest *req=webkit_navigation_policy_decision_get_request(nav);const gchar *uri=webkit_uri_request_get_uri(req);if(is_internal_uri(uri)){webkit_policy_decision_use(decision);return TRUE;}webkit_policy_decision_ignore(decision);if(uri&&*uri)g_app_info_launch_default_for_uri(uri,NULL,NULL);return TRUE;}
static void shutdown_app(GtkWidget *w,gpointer d){if(php_pid>0){kill(php_pid,SIGTERM);waitpid(php_pid,NULL,WNOHANG);}gtk_main_quit();}
int main(int argc,char **argv){
 gtk_init(&argc,&argv);const char *root=g_getenv("DCP_ROOT");gchar *exe_dir=NULL;if(!root){char exe[PATH_MAX];ssize_t n=readlink("/proc/self/exe",exe,sizeof(exe)-1);if(n>0){exe[n]=0;exe_dir=g_path_get_dirname(exe);root=exe_dir;}else root=".";}
 dcp_port=DCP_LOCAL_PORT;if(!dcp_port_available()){g_printerr("DCP local port %u is already in use. Close its owner; DCP will not switch to a random port.\\n",dcp_port);return 1;}
 gchar *platform=g_build_filename(root,"platform",NULL);gchar *pub=g_build_filename(platform,"public",NULL);gchar *router=g_build_filename(pub,"router.php",NULL);gchar *php=g_build_filename(root,"runtime","php","bin","php",NULL);
 if(!g_file_test(php,G_FILE_TEST_IS_EXECUTABLE)){g_free(php);php=g_find_program_in_path("php");}
 if(!php){g_printerr("DCP requires PHP CLI. Run Run-DCP.sh to install runtime dependencies.\n");return 1;}
 gchar *listen=g_strdup_printf("127.0.0.1:%u",dcp_port);gchar *args[]={php,"-d","upload_max_filesize=1G","-d","post_max_size=1G","-d","max_input_time=0","-d","max_execution_time=0","-S",listen,"-t",pub,router,NULL};GError *error=NULL;
 if(!g_spawn_async(platform,args,NULL,G_SPAWN_DO_NOT_REAP_CHILD,NULL,NULL,&php_pid,&error)){g_printerr("DCP PHP start failed: %s\n",error->message);return 1;}
 GtkWidget *window=gtk_window_new(GTK_WINDOW_TOPLEVEL);gtk_window_set_title(GTK_WINDOW(window),"DCP — Desktop Connector Platform");gtk_window_set_default_size(GTK_WINDOW(window),1280,840);gtk_window_set_position(GTK_WINDOW(window),GTK_WIN_POS_CENTER);
 WebKitWebView *view=WEBKIT_WEB_VIEW(webkit_web_view_new());g_signal_connect(view,"decide-policy",G_CALLBACK(navigation_policy),NULL);gtk_container_add(GTK_CONTAINER(window),GTK_WIDGET(view));g_signal_connect(window,"destroy",G_CALLBACK(shutdown_app),NULL);g_timeout_add(300,ready,view);gtk_widget_show_all(window);gtk_main();
 g_free(platform);g_free(pub);g_free(router);g_free(php);g_free(exe_dir);g_free(listen);return 0;
}
