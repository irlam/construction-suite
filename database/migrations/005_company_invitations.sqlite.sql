CREATE TABLE IF NOT EXISTS company_invitations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT NOT NULL UNIQUE,
    organization_id INTEGER NOT NULL,
    project_id INTEGER NULL,
    invited_by INTEGER NOT NULL,
    email TEXT NOT NULL,
    role_key TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    accepted_at INTEGER NULL,
    revoked_at INTEGER NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_company_invites_org ON company_invitations(organization_id, expires_at);
