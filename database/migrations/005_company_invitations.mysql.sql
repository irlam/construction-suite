CREATE TABLE IF NOT EXISTS company_invitations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    invited_by BIGINT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    role_key VARCHAR(40) NOT NULL,
    expires_at BIGINT NOT NULL,
    accepted_at BIGINT NULL,
    revoked_at BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_company_invites_token (token_hash),
    KEY idx_company_invites_org (organization_id, expires_at),
    CONSTRAINT fk_company_invites_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_company_invites_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_company_invites_author FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
