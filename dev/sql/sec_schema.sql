-- Schema minimo delle tabelle sec_* di giga-core, SOLO per l'harness di
-- test locale (dev/). Non è una migration reale, non va applicata a nessun
-- progetto consumer — riproduce lo schema reale di wos-pro/cms-neviobianchi
-- (001_base_setup.sql) con l'aggiunta di sec_permissions.resource_type
-- (Decisione #4), che nei progetti reali non è stata ancora applicata.
--
-- Uso: docker exec -i giga-cms-test-db mariadb -uroot -proot giga_cms_test < dev/sql/sec_schema.sql

CREATE TABLE IF NOT EXISTS sec_roles (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                 VARCHAR(50)  NOT NULL,
    slug                 VARCHAR(50)  NOT NULL,
    description          VARCHAR(255) DEFAULT NULL,
    permissions_version  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY name (name),
    UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sec_permissions (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    module         VARCHAR(50) NOT NULL,
    action         VARCHAR(50) NOT NULL,
    resource_type  VARCHAR(20) DEFAULT NULL COMMENT 'NULL = permesso di sistema, altrimenti slug del Content Type',
    PRIMARY KEY (id),
    UNIQUE KEY unique_permission (module, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sec_role_permissions (
    role_id        INT UNSIGNED NOT NULL,
    permission_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY fk_rp_permission (permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES sec_roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES sec_permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO sec_roles (name, slug, description) VALUES
('Amministratore', 'admin', 'Accesso completo'),
('Editor', 'editor', 'Gestione contenuti');

-- Un permesso di sistema con resource_type IS NULL, per testare la regola
-- anti-collisione della Decisione #4 (uno slug di Content Type non può
-- coincidere con un module di sistema esistente).
INSERT INTO sec_permissions (module, action, resource_type) VALUES
('settings', 'view', NULL);
