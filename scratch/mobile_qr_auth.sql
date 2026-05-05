CREATE TABLE IF NOT EXISTS mobile_access_tokens (
    idMobileAccessToken INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token_prefix VARCHAR(16) NOT NULL,
    device_id VARCHAR(140) NOT NULL,
    device_name VARCHAR(140) NOT NULL DEFAULT 'Mobile device',
    platform VARCHAR(40) NULL,
    app_version VARCHAR(40) NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL DEFAULT NULL,
    revoked_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (idMobileAccessToken),
    UNIQUE KEY uniq_mobile_token_hash (token_hash),
    INDEX idx_mobile_token_user (user_id),
    INDEX idx_mobile_token_device (user_id, device_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trusted_mobile_devices (
    idTrustedDevice INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    device_id VARCHAR(140) NOT NULL,
    device_name VARCHAR(140) NOT NULL DEFAULT 'Mobile device',
    platform VARCHAR(40) NULL,
    app_version VARCHAR(40) NULL,
    trusted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NULL DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (idTrustedDevice),
    UNIQUE KEY uniq_user_device (user_id, device_id),
    INDEX idx_trusted_user (user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qr_trust_sessions (
    idQrTrustSession INT NOT NULL AUTO_INCREMENT,
    session_token CHAR(64) NOT NULL,
    user_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL DEFAULT NULL,
    trusted_device_id INT NULL DEFAULT NULL,
    PRIMARY KEY (idQrTrustSession),
    UNIQUE KEY uniq_qr_trust_token (session_token),
    INDEX idx_qr_trust_lookup (session_token, status, expires_at),
    INDEX idx_qr_trust_user (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qr_login_sessions (
    idQrLoginSession INT NOT NULL AUTO_INCREMENT,
    session_token CHAR(64) NOT NULL,
    browser_session_id VARCHAR(190) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    requested_ip VARCHAR(64) NULL DEFAULT NULL,
    requested_user_agent VARCHAR(255) NULL DEFAULT NULL,
    approved_user_id INT NULL DEFAULT NULL,
    approved_device_id INT NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    approved_at DATETIME NULL DEFAULT NULL,
    consumed_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (idQrLoginSession),
    UNIQUE KEY uniq_qr_login_token (session_token),
    INDEX idx_qr_login_lookup (session_token, status, expires_at),
    INDEX idx_qr_login_browser (browser_session_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;