CREATE TABLE IF NOT EXISTS module_sessions (
    token_hash TEXT PRIMARY KEY,
    audience_hash TEXT NOT NULL,
    instance_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,
    revoked_at INTEGER NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_module_sessions_expiry ON module_sessions(expires_at);
