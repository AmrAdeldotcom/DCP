-- Apply once to an existing DCP Cloud database before uploading the new index.php.
-- Existing account-owned device identities and tokens are preserved.
ALTER TABLE devices MODIFY user_id BIGINT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS pairing_codes (
 id CHAR(36) PRIMARY KEY,
 owner_device CHAR(36) NOT NULL,
 code_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(owner_device) REFERENCES devices(id) ON DELETE CASCADE,
 INDEX(owner_device,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pairing_requests (
 id CHAR(36) PRIMARY KEY,
 pairing_code_id CHAR(36) NOT NULL,
 owner_device CHAR(36) NOT NULL,
 requester_device CHAR(36) NOT NULL,
 status ENUM('pending','accepted','rejected','expired') NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 decided_at DATETIME NULL,
 FOREIGN KEY(pairing_code_id) REFERENCES pairing_codes(id) ON DELETE CASCADE,
 FOREIGN KEY(owner_device) REFERENCES devices(id) ON DELETE CASCADE,
 FOREIGN KEY(requester_device) REFERENCES devices(id) ON DELETE CASCADE,
 UNIQUE KEY uq_pairing_code_request(pairing_code_id),
 INDEX(owner_device,status,expires_at),
 INDEX(requester_device,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS device_pairings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 owner_device CHAR(36) NOT NULL,
 peer_device CHAR(36) NOT NULL,
 scopes TEXT NOT NULL,
 presence_paused_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 revoked_at DATETIME NULL,
 FOREIGN KEY(owner_device) REFERENCES devices(id) ON DELETE CASCADE,
 FOREIGN KEY(peer_device) REFERENCES devices(id) ON DELETE CASCADE,
 UNIQUE KEY uq_device_pair(owner_device,peer_device),
 INDEX(peer_device,revoked_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add the pause timestamp safely when upgrading an existing pairing table.
SET @dcp_presence_paused_column_exists = (
 SELECT COUNT(*) FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='device_pairings' AND COLUMN_NAME='presence_paused_at'
);
SET @dcp_presence_pause_ddl = IF(
 @dcp_presence_paused_column_exists=0,
 'ALTER TABLE device_pairings ADD COLUMN presence_paused_at DATETIME NULL AFTER scopes',
 'SELECT 1'
);
PREPARE dcp_presence_pause_stmt FROM @dcp_presence_pause_ddl;
EXECUTE dcp_presence_pause_stmt;
DEALLOCATE PREPARE dcp_presence_pause_stmt;

CREATE TABLE IF NOT EXISTS api_rate_limits (
 bucket_key CHAR(64) PRIMARY KEY,
 hits INT UNSIGNED NOT NULL DEFAULT 0,
 expires_at DATETIME NOT NULL,
 INDEX(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Scope API bearer tokens so Cloud MCP clients receive read-only capabilities only.
SET @dcp_api_token_scopes_exists = (
 SELECT COUNT(*) FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_tokens' AND COLUMN_NAME='scopes_json'
);
SET @dcp_api_token_scopes_ddl = IF(
 @dcp_api_token_scopes_exists=0,
 'ALTER TABLE api_tokens ADD COLUMN scopes_json TEXT NOT NULL DEFAULT ''["*"]'' AFTER expires_at',
 'SELECT 1'
);
PREPARE dcp_api_token_scopes_stmt FROM @dcp_api_token_scopes_ddl;
EXECUTE dcp_api_token_scopes_stmt;
DEALLOCATE PREPARE dcp_api_token_scopes_stmt;

-- DCP Resource Mesh: metadata and grants only; secrets remain in owner-local vaults.
CREATE TABLE IF NOT EXISTS cloud_teams (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 owner_user_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX(owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cloud_team_members (
 team_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 role ENUM('owner','admin','member') NOT NULL DEFAULT 'member',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(team_id,user_id),
 FOREIGN KEY(team_id) REFERENCES cloud_teams(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cloud_resources (
 id CHAR(36) PRIMARY KEY,
 owner_user_id BIGINT UNSIGNED NULL,
 owner_device_id CHAR(36) NOT NULL,
 kind VARCHAR(40) NOT NULL,
 name VARCHAR(160) NOT NULL,
 provider VARCHAR(100) NOT NULL DEFAULT '',
 model VARCHAR(160) NOT NULL DEFAULT '',
 endpoint_class VARCHAR(40) NOT NULL DEFAULT 'local',
 operations_json JSON NOT NULL,
 metadata_json JSON NOT NULL,
 capacity_json JSON NOT NULL,
 data_classification ENUM('public','internal','confidential','restricted') NOT NULL DEFAULT 'internal',
 locality ENUM('device','region','cloud') NOT NULL DEFAULT 'device',
 secret_ref VARCHAR(160) NOT NULL DEFAULT '',
 health_status ENUM('unknown','online','degraded','offline','error') NOT NULL DEFAULT 'unknown',
 health_at DATETIME NULL,
 published TINYINT(1) NOT NULL DEFAULT 0,
 revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(owner_device_id) REFERENCES devices(id) ON DELETE CASCADE,
 INDEX(owner_user_id,revoked_at),
 INDEX(owner_device_id,health_status),
 INDEX(kind,provider,model,health_status,published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cloud_resource_grants (
 id CHAR(36) PRIMARY KEY,
 resource_id CHAR(36) NOT NULL,
 grantee_type ENUM('user','team','client','device') NOT NULL,
 grantee_id VARCHAR(160) NOT NULL,
 operations_json JSON NOT NULL,
 quota_limit INT UNSIGNED NOT NULL DEFAULT 0,
 concurrency_limit SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 expires_at DATETIME NULL,
 status ENUM('active','revoked') NOT NULL DEFAULT 'active',
 generation INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 revoked_at DATETIME NULL,
 FOREIGN KEY(resource_id) REFERENCES cloud_resources(id) ON DELETE CASCADE,
 INDEX(grantee_type,grantee_id,status,expires_at),
 INDEX(resource_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cloud_resource_invocations (
 id CHAR(36) PRIMARY KEY,
 resource_id CHAR(36) NOT NULL,
 grant_id CHAR(36) NULL,
 actor_user_id BIGINT UNSIGNED NULL,
 actor_device_id CHAR(36) NULL,
 operation VARCHAR(64) NOT NULL,
 status VARCHAR(24) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(resource_id) REFERENCES cloud_resources(id) ON DELETE CASCADE,
 FOREIGN KEY(grant_id) REFERENCES cloud_resource_grants(id) ON DELETE SET NULL,
 FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(actor_device_id) REFERENCES devices(id) ON DELETE SET NULL,
 INDEX(grant_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Target-device approval inbox for remote terminal requests. No execution endpoint is exposed until signed delivery is implemented.
CREATE TABLE IF NOT EXISTS cloud_remote_terminal_requests (
 id CHAR(36) PRIMARY KEY,
 source_device CHAR(36) NOT NULL,
 target_device CHAR(36) NOT NULL,
 requester_user_id BIGINT UNSIGNED NULL,
 command_text MEDIUMTEXT NOT NULL,
 command_sha256 CHAR(64) NOT NULL,
 working_directory TEXT NOT NULL,
 shell_name VARCHAR(24) NOT NULL,
 timeout_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 30,
 status ENUM('pending','approved','rejected','expired','dispatched','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
 approval_hash CHAR(64) NULL,
 decided_by_device CHAR(36) NULL,
 result_sha256 CHAR(64) NULL,
 result_summary TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 decided_at DATETIME NULL,
 completed_at DATETIME NULL,
 FOREIGN KEY(source_device) REFERENCES devices(id) ON DELETE CASCADE,
 FOREIGN KEY(target_device) REFERENCES devices(id) ON DELETE CASCADE,
 FOREIGN KEY(requester_user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(decided_by_device) REFERENCES devices(id) ON DELETE SET NULL,
 INDEX(target_device,status,expires_at),
 INDEX(source_device,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cloud_device_signing_keys (
 device_id CHAR(36) PRIMARY KEY,
 public_key VARCHAR(64) NOT NULL,
 key_id CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 revoked_at DATETIME NULL,
 FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cloud_remote_terminal_dispatches (
 request_id CHAR(36) PRIMARY KEY,
 envelope_id CHAR(36) NOT NULL UNIQUE,
 payload_json MEDIUMTEXT NOT NULL,
 signature VARCHAR(128) NOT NULL,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(request_id) REFERENCES cloud_remote_terminal_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cloud_remote_terminal_results (
 request_id CHAR(36) PRIMARY KEY,
 envelope_id CHAR(36) NOT NULL,
 result_sha256 CHAR(64) NOT NULL,
 result_json MEDIUMTEXT NOT NULL,
 signature VARCHAR(128) NOT NULL,
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(request_id) REFERENCES cloud_remote_terminal_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
