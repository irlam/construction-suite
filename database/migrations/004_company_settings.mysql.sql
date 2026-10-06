CREATE TABLE IF NOT EXISTS company_settings (
    organization_id BIGINT UNSIGNED NOT NULL,
    contact_email VARCHAR(190) NOT NULL DEFAULT '',
    brand_colour CHAR(7) NOT NULL DEFAULT '#2563eb',
    logo_mime VARCHAR(32) NULL,
    logo_base64 MEDIUMTEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (organization_id),
    CONSTRAINT fk_company_settings_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS company_project_modules (
    project_id BIGINT UNSIGNED NOT NULL,
    module_key VARCHAR(80) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, module_key),
    CONSTRAINT fk_company_project_modules_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
