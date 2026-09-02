<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Asset Library (Immagini, Video, PDF, Documenti) — libreria unica.
 *
 * Metadati V1 espliciti nel documento: alt, title, caption, credit,
 * copyright. focal_point è colonna riservata (UI di crop in fase
 * successiva). is_public distingue asset pubblici (URL diretta) da
 * privati (serviti solo tramite endpoint autorizzato, Invariante #10) —
 * questa migration non implementa l'endpoint né l'upload effettivo, solo
 * lo schema.
 *
 * mime_type qui presuppone già verificato lato server (mai il MIME
 * dichiarato dal client) — quella verifica vive nel layer di upload, non
 * ancora costruito.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE media (
                id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                path        VARCHAR(500) NOT NULL,
                filename    VARCHAR(255) NOT NULL,
                mime_type   VARCHAR(100) NOT NULL,
                kind        VARCHAR(20)  NOT NULL,
                size        INT UNSIGNED NOT NULL,
                width       INT UNSIGNED NULL,
                height      INT UNSIGNED NULL,
                is_public   BOOLEAN      NOT NULL DEFAULT TRUE,
                alt         VARCHAR(255) NULL,
                title       VARCHAR(255) NULL,
                caption     VARCHAR(500) NULL,
                credit      VARCHAR(255) NULL,
                copyright   VARCHAR(255) NULL,
                focal_point VARCHAR(20)  NULL,
                created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_media_kind (kind),
                KEY idx_media_is_public (is_public)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE media");
    }
};
