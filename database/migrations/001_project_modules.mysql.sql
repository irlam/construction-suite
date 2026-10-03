CREATE TABLE IF NOT EXISTS project_modules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT UNSIGNED NOT NULL,
    module_key VARCHAR(80) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    external_project_ref VARCHAR(190) NULL,
    config_json TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_modules_project_module (project_id, module_key),
    KEY idx_project_modules_project (project_id),
    CONSTRAINT fk_project_modules_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
