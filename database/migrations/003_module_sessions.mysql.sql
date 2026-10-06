CREATE TABLE IF NOT EXISTS module_sessions (
    token_hash CHAR(64) NOT NULL,
    audience_hash CHAR(64) NOT NULL,
    instance_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    expires_at BIGINT NOT NULL,
    revoked_at BIGINT NULL,
    PRIMARY KEY (token_hash),
    KEY idx_module_sessions_expiry (expires_at),
    CONSTRAINT fk_module_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
