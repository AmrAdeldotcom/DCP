CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 display_name VARCHAR(120) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE devices (
 id CHAR(36) PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 label VARCHAR(120) NOT NULL,
 platform VARCHAR(40) NOT NULL DEFAULT 'unknown',
 token_hash CHAR(64) NOT NULL UNIQUE,
 last_seen_at DATETIME NULL,
 revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX(user_id,revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE api_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE connection_requests (
 id CHAR(36) PRIMARY KEY,
 source_device CHAR(36) NOT NULL,
 target_device CHAR(36) NOT NULL,
 status ENUM('pending','accepted','rejected','expired') NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 INDEX(target_device,status,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE signaling_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 request_id CHAR(36) NOT NULL,
 sender_device CHAR(36) NOT NULL,
 recipient_device CHAR(36) NOT NULL,
 envelope MEDIUMBLOB NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 INDEX(recipient_device,id), INDEX(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE guest_invites (
 id CHAR(36) PRIMARY KEY,
 owner_device CHAR(36) NOT NULL,
 invite_hash CHAR(64) NOT NULL UNIQUE,
 guest_device CHAR(36) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 INDEX(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pairing_codes (
 id CHAR(36) PRIMARY KEY,
 owner_device CHAR(36) NOT NULL,
 code_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(owner_device) REFERENCES devices(id) ON DELETE CASCADE,
 INDEX(owner_device,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE pairing_requests (
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
CREATE TABLE device_pairings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 owner_device CHAR(36) NOT NULL,
 peer_device CHAR(36) NOT NULL,
 scopes TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 revoked_at DATETIME NULL,
 FOREIGN KEY(owner_device) REFERENCES devices(id) ON DELETE CASCADE,
 FOREIGN KEY(peer_device) REFERENCES devices(id) ON DELETE CASCADE,
 UNIQUE KEY uq_device_pair(owner_device,peer_device),
 INDEX(peer_device,revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
