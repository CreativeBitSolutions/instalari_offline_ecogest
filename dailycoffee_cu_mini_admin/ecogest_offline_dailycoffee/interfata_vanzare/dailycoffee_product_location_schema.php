<?php

function dailycoffeeLocalProductLocationEnsureSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS produse_servicii_locatii (
        cod_produs INTEGER NOT NULL,
        cod_locatie INTEGER NOT NULL,
        activ INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (cod_produs, cod_locatie)
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_produse_servicii_locatii_locatie ON produse_servicii_locatii (cod_locatie, activ)');

    $count = (int)$pdo->query('SELECT COUNT(*) FROM produse_servicii_locatii WHERE cod_locatie = 2')->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT OR IGNORE INTO produse_servicii_locatii (cod_produs, cod_locatie, activ)
            SELECT cod_produs, 2, 1
            FROM produse_servicii
            WHERE cod_produs > 0 AND activ = 1");
    }
}

