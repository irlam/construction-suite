CREATE TABLE IF NOT EXISTS company_settings (
    organization_id INTEGER PRIMARY KEY,
    contact_email TEXT NOT NULL DEFAULT '',
    brand_colour TEXT NOT NULL DEFAULT '#2563eb',
    logo_mime TEXT NULL,
    logo_base64 TEXT NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS company_project_modules (
    project_id INTEGER NOT NULL,
    module_key TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, module_key),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
