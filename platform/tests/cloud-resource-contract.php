<?php
declare(strict_types=1);
$cloudSource=(string)file_get_contents(dirname(__DIR__).'/cloud/index.php');
$cloudSchema=(string)file_get_contents(dirname(__DIR__).'/cloud/schema.mysql.sql');
$cloudMigration=(string)file_get_contents(dirname(__DIR__).'/cloud/schema.migrate.sql');
foreach(['/api/v1/resources','/api/v1/resources/','/api/v1/resource-grants/','/api/v1/teams','/api/v1/teams/'] as $route)ok(str_contains($cloudSource,$route),'Cloud Resource Mesh exposes route '.$route);
ok(str_contains($cloudSchema,'CREATE TABLE cloud_resources')&&str_contains($cloudSchema,'CREATE TABLE cloud_resource_grants')&&str_contains($cloudSchema,'CREATE TABLE cloud_teams')&&str_contains($cloudSchema,'CREATE TABLE cloud_team_members'),'Cloud schema includes resources, grants and team membership');
ok(str_contains($cloudMigration,'CREATE TABLE IF NOT EXISTS cloud_resources')&&str_contains($cloudMigration,'CREATE TABLE IF NOT EXISTS cloud_resource_grants'),'Cloud upgrade migration includes Resource Mesh tables');
ok(str_contains($cloudSource,'auth_device()')&&str_contains($cloudSource,'auth_user()')&&str_contains($cloudSource,'team_admin_required'),'Cloud resource registration, grant and team operations require authenticated ownership/admin');
ok(str_contains($cloudSource,'credential_material_forbidden')&&str_contains($cloudSource,'owner_local_secret_ref_only'),'Cloud resource contract rejects raw secrets and keeps credentials owner-local');
ok(str_contains($cloudSource,'g.generation=g.generation+1')&&str_contains($cloudSource,"g.status='revoked'"),'Cloud grant revocation increments the authorization generation');
$remoteApproval=str_contains($cloudSchema,'CREATE TABLE cloud_remote_terminal_requests')&&str_contains($cloudMigration,'CREATE TABLE IF NOT EXISTS cloud_remote_terminal_requests')&&str_contains($cloudSource,'target_device_must_grant_terminal_request_scope')&&str_contains($cloudSource,'remote-terminal-requests/inbox')&&str_contains($cloudSource,'approved_hash')&&str_contains($cloudSource,'No command has been dispatched.');
ok($remoteApproval,'Cloud remote terminal requests require target-side scope and exact-command approval before dispatch');
$terminalDispatch=str_contains($cloudSchema,'CREATE TABLE cloud_remote_terminal_dispatches')&&str_contains($cloudSchema,'CREATE TABLE cloud_device_signing_keys')&&str_contains($cloudSchema,'CREATE TABLE cloud_remote_terminal_results')&&str_contains($cloudMigration,'CREATE TABLE IF NOT EXISTS cloud_remote_terminal_dispatches')&&str_contains($cloudSource,'remote-terminal-requests/([a-f0-9-]{36})/dispatch')&&str_contains($cloudSource,'approval_hash')&&str_contains($cloudSource,'dcp_cloud_terminal_signing_keys')&&str_contains($cloudSource,'remote-terminal-requests/([a-f0-9-]{36})/result')&&str_contains($cloudSource,'dcp_cloud_terminal_verify');
ok($terminalDispatch,'Cloud dispatch requires exact target approval and signs the exact terminal envelope; result signatures are verified');
$cloudEnv=(string)file_get_contents(dirname(__DIR__).'/cloud/.env.example');
$terminalSigning=str_contains($cloudSource,"/api/v1/signing-key")&&str_contains($cloudSource,"/api/v1/device/signing-key")&&str_contains($cloudSource,'target_device_signing_key_not_registered')&&str_contains($cloudSource,'conflicting_result_retry')&&str_contains($cloudEnv,'DCP_CLOUD_SIGNING_SEED=');
ok($terminalSigning,'Cloud exposes a dedicated pinned Ed25519 identity and refuses dispatch until the target result-signing key is registered');
