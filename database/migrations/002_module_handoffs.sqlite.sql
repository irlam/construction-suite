CREATE TABLE IF NOT EXISTS module_handoffs (
    code_hash TEXT PRIMARY KEY,
    state_hash TEXT NOT NULL,
    audience_hash TEXT NOT NULL,
    instance_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,
    used_at INTEGER NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_handoffs_expiry ON module_handoffs(expires_at);
