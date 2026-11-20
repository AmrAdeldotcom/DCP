<?php
declare(strict_types=1);
final class Database {
 public static function connect(array $c):PDO {
  $dir=dirname($c['database']);if(!is_dir($dir))mkdir($dir,0700,true);
  $db=new PDO('sqlite:'.$c['database'],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
  $db->exec('PRAGMA journal_mode=WAL');$db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=5000');return $db;
 }
 public static function migrate(PDO $db):void {
  $db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY,value TEXT NOT NULL,updated_at TEXT NOT NULL)");
  $db->exec("CREATE TABLE IF NOT EXISTS providers (id TEXT PRIMARY KEY,name TEXT NOT NULL,driver TEXT NOT NULL,base_url TEXT NOT NULL,model TEXT NOT NULL,api_key_enc TEXT NOT NULL DEFAULT '',enabled INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL,updated_at TEXT NOT NULL)");
  $db->exec("CREATE TABLE IF NOT EXISTS translations (locale TEXT NOT NULL,key TEXT NOT NULL,value TEXT NOT NULL,updated_at TEXT NOT NULL,PRIMARY KEY(locale,key))");
  $db->exec("CREATE TABLE IF NOT EXISTS tasks (id TEXT PRIMARY KEY,title TEXT NOT NULL,description TEXT NOT NULL DEFAULT '',status TEXT NOT NULL DEFAULT 'queued',payload TEXT NOT NULL DEFAULT '{}',result TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL,updated_at TEXT NOT NULL)");
  $db->exec("CREATE TABLE IF NOT EXISTS devices (id TEXT PRIMARY KEY,name TEXT NOT NULL,platform TEXT NOT NULL DEFAULT '',status TEXT NOT NULL DEFAULT 'pending',token_hash TEXT NOT NULL,created_at TEXT NOT NULL,last_seen TEXT)");
  $db->exec("CREATE TABLE IF NOT EXISTS invitations (id TEXT PRIMARY KEY,email TEXT NOT NULL,device_name TEXT NOT NULL,token_hash TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',expires_at TEXT NOT NULL,created_at TEXT NOT NULL)");
  $db->exec("CREATE TABLE IF NOT EXISTS reports (id TEXT PRIMARY KEY,title TEXT NOT NULL,body TEXT NOT NULL,created_at TEXT NOT NULL,email_status TEXT NOT NULL DEFAULT 'not_sent')");
  $db->exec("CREATE TABLE IF NOT EXISTS mail_messages (id TEXT PRIMARY KEY,uid TEXT NOT NULL,from_email TEXT NOT NULL DEFAULT '',subject TEXT NOT NULL DEFAULT '',message_date TEXT NOT NULL DEFAULT '',preview TEXT NOT NULL DEFAULT '',category TEXT NOT NULL DEFAULT 'general',received_at TEXT NOT NULL)");
  $db->exec("CREATE TABLE IF NOT EXISTS audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT,event TEXT NOT NULL,details TEXT NOT NULL,created_at TEXT NOT NULL)");
  $db->exec("CREATE TABLE IF NOT EXISTS notifications (id TEXT PRIMARY KEY,title TEXT NOT NULL,body TEXT NOT NULL DEFAULT '',kind TEXT NOT NULL DEFAULT 'info',entity_id TEXT NOT NULL DEFAULT '',read_at TEXT,created_at TEXT NOT NULL)");
  $db->exec("CREATE TABLE IF NOT EXISTS chat_sessions (id TEXT PRIMARY KEY,title TEXT NOT NULL,provider_id TEXT NOT NULL,created_at TEXT NOT NULL,updated_at TEXT NOT NULL)");
  $db->exec("CREATE TABLE IF NOT EXISTS chat_messages (id INTEGER PRIMARY KEY AUTOINCREMENT,session_id TEXT NOT NULL,role TEXT NOT NULL,content TEXT NOT NULL,created_at TEXT NOT NULL,FOREIGN KEY(session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_chat_messages_session ON chat_messages(session_id,id)");
  $cols=$db->query('PRAGMA table_info(tasks)')->fetchAll(PDO::FETCH_COLUMN,1);if(!in_array('target_device_id',$cols,true))$db->exec("ALTER TABLE tasks ADD COLUMN target_device_id TEXT NOT NULL DEFAULT 'local'");
 }
}
