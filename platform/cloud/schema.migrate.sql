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
