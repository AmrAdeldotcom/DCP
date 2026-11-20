#include <gtk/gtk.h>
#include <webkit2/webkit2.h>
#include <glib/gstdio.h>
#include <signal.h>
#include <unistd.h>
#include <sys/wait.h>
#include <stdio.h>
#include <limits.h>
#include <stdlib.h>
#include <string.h>
#define PORT "18765"
static GPid php_pid=0;
static gboolean ready(gpointer data){
 WebKitWebView *view=WEBKIT_WEB_VIEW(data);GError *error=NULL;
 GSocketClient *client=g_socket_client_new();
 GSocketConnection *connection=g_socket_client_connect_to_host(client,"127.0.0.1",18765,NULL,&error);
 if(connection){
  g_object_unref(connection);g_object_unref(client);
  webkit_web_view_load_uri(view,"http://127.0.0.1:" PORT "/");
  return G_SOURCE_REMOVE;
 }
 g_clear_error(&error);g_object_unref(client);return G_SOURCE_CONTINUE;
}
static gboolean is_internal_uri(const gchar *uri){GUri *u=g_uri_parse(uri,G_URI_FLAGS_NONE,NULL);if(!u)return FALSE;const gchar *scheme=g_uri_get_scheme(u),*host=g_uri_get_host(u);gboolean ok=scheme&&host&&(!g_ascii_strcasecmp(scheme,"http")||!g_ascii_strcasecmp(scheme,"https"))&&(!g_ascii_strcasecmp(host,"127.0.0.1")||!g_ascii_strcasecmp(host,"localhost"));g_uri_unref(u);return ok;}
static gboolean navigation_policy(WebKitWebView *view,WebKitPolicyDecision *decision,WebKitPolicyDecisionType type,gpointer data){if(type!=WEBKIT_POLICY_DECISION_TYPE_NAVIGATION_ACTION&&type!=WEBKIT_POLICY_DECISION_TYPE_NEW_WINDOW_ACTION)return FALSE;WebKitNavigationPolicyDecision *nav=WEBKIT_NAVIGATION_POLICY_DECISION(decision);WebKitURIRequest *req=webkit_navigation_policy_decision_get_request(nav);const gchar *uri=webkit_uri_request_get_uri(req);if(is_internal_uri(uri)){webkit_policy_decision_use(decision);return TRUE;}webkit_policy_decision_ignore(decision);if(uri&&*uri)g_app_info_launch_default_for_uri(uri,NULL,NULL);return TRUE;}
static void shutdown_app(GtkWidget *w,gpointer d){if(php_pid>0){kill(php_pid,SIGTERM);waitpid(php_pid,NULL,WNOHANG);}gtk_main_quit();}
int main(int argc,char **argv){
 gtk_init(&argc,&argv);const char *root=g_getenv("DCP_ROOT");gchar *exe_dir=NULL;if(!root){char exe[PATH_MAX];ssize_t n=readlink("/proc/self/exe",exe,sizeof(exe)-1);if(n>0){exe[n]=0;exe_dir=g_path_get_dirname(exe);root=exe_dir;}else root=".";}
 gchar *platform=g_build_filename(root,"platform",NULL);gchar *pub=g_build_filename(platform,"public",NULL);gchar *router=g_build_filename(pub,"router.php",NULL);gchar *php=g_build_filename(root,"runtime","php","bin","php",NULL);
 if(!g_file_test(php,G_FILE_TEST_IS_EXECUTABLE)){g_free(php);php=g_strdup("php");}
 gchar *args[]={php,"-S","127.0.0.1:" PORT,"-t",pub,router,NULL};GError *error=NULL;
 if(!g_spawn_async(platform,args,NULL,G_SPAWN_DO_NOT_REAP_CHILD,NULL,NULL,&php_pid,&error)){g_printerr("DCP PHP start failed: %s\n",error->message);return 1;}
 GtkWidget *window=gtk_window_new(GTK_WINDOW_TOPLEVEL);gtk_window_set_title(GTK_WINDOW(window),"DCP — Desktop Connector Platform");gtk_window_set_default_size(GTK_WINDOW(window),1280,840);gtk_window_set_position(GTK_WINDOW(window),GTK_WIN_POS_CENTER);
 WebKitWebView *view=WEBKIT_WEB_VIEW(webkit_web_view_new());g_signal_connect(view,"decide-policy",G_CALLBACK(navigation_policy),NULL);gtk_container_add(GTK_CONTAINER(window),GTK_WIDGET(view));g_signal_connect(window,"destroy",G_CALLBACK(shutdown_app),NULL);g_timeout_add(300,ready,view);gtk_widget_show_all(window);gtk_main();
 g_free(platform);g_free(pub);g_free(router);g_free(php);g_free(exe_dir);return 0;
}
