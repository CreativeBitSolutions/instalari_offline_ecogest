<?php
declare(strict_types=1);

function restaurant_sqlite_schema_statements(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS admins_12 (
            admin_id INTEGER PRIMARY KEY,
            admin_firstname TEXT DEFAULT '',
            admin_lastname TEXT DEFAULT '',
            admin_email_address TEXT DEFAULT '',
            admin_password TEXT DEFAULT '',
            rank TEXT DEFAULT '',
            locatie INTEGER DEFAULT 0,
            lucreaza_la TEXT DEFAULT 'restaurant',
            conectat INTEGER DEFAULT 0,
            nr_tableta INTEGER DEFAULT 0,
            cod_tableta INTEGER DEFAULT 0,
            cod_2fa_tableta INTEGER DEFAULT 0,
            data_generare_cod_2fa_tableta TEXT,
            active INTEGER DEFAULT 1
        )",
        "CREATE TABLE IF NOT EXISTS conectari_operatori (
            id_conectare INTEGER PRIMARY KEY AUTOINCREMENT,
            id_operator INTEGER DEFAULT 0,
            nume_operator TEXT DEFAULT '',
            login_time TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS ultima_conexiune (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER DEFAULT 0,
            locatie INTEGER DEFAULT 0,
            timestamp TEXT DEFAULT CURRENT_TIMESTAMP,
            device_uid TEXT DEFAULT '',
            device_ip TEXT DEFAULT '',
            device_user_agent TEXT DEFAULT ''
        )",
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_ultima_conexiune_device ON ultima_conexiune(locatie, device_uid)",
        "CREATE TABLE IF NOT EXISTS ultim_bon_conectat (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            locatie INTEGER DEFAULT 0,
            nr_bon INTEGER DEFAULT 0,
            timestamp TEXT DEFAULT CURRENT_TIMESTAMP,
            device_uid TEXT DEFAULT '',
            device_ip TEXT DEFAULT '',
            device_user_agent TEXT DEFAULT ''
        )",
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_ultim_bon_conectat_device ON ultim_bon_conectat(locatie, device_uid)",
        "CREATE TABLE IF NOT EXISTS setari_platforma (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            comunicare_anaf INTEGER DEFAULT 0,
            mod_touch INTEGER DEFAULT 0,
            activare_listener INTEGER DEFAULT 0,
            cu_imprimanta INTEGER DEFAULT 1,
            autologin_restaurant INTEGER DEFAULT 0,
            listare_automata_comenzi_site INTEGER DEFAULT 1,
            omite_listare_bar_la_trimitere INTEGER DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS loc_mese_12 (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cod_locatie INTEGER DEFAULT 0,
            den_loc TEXT DEFAULT '',
            denumire TEXT DEFAULT '',
            serie_casa_marcat TEXT DEFAULT '',
            nui INTEGER DEFAULT 0,
            serie_memorie_fiscala TEXT DEFAULT ''
        )",
        "CREATE INDEX IF NOT EXISTS idx_loc_mese_12_cod_locatie ON loc_mese_12(cod_locatie)",
        "CREATE TABLE IF NOT EXISTS mese (
            cod_masa INTEGER PRIMARY KEY,
            nume_masa TEXT DEFAULT '',
            cod_locatie INTEGER DEFAULT 0,
            categorie_masa TEXT DEFAULT 'Sala',
            tip_masa TEXT DEFAULT 'simpla',
            stare INTEGER DEFAULT 0,
            cod_bratara TEXT DEFAULT '',
            date_posesor TEXT DEFAULT '',
            vandut_intrare INTEGER DEFAULT 0,
            masa_comenzi_online INTEGER DEFAULT 0,
            sold REAL DEFAULT 0
        )",
        "CREATE INDEX IF NOT EXISTS idx_mese_locatie ON mese(cod_locatie)",
        "CREATE TABLE IF NOT EXISTS categorii (
            id_categorie INTEGER PRIMARY KEY,
            den_categ TEXT DEFAULT '',
            se_vinde INTEGER DEFAULT 1
        )",
        "CREATE TABLE IF NOT EXISTS categorii_locatii (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_categorie INTEGER DEFAULT 0,
            cod_locatie INTEGER DEFAULT 0
        )",
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_categorii_locatii ON categorii_locatii(id_categorie, cod_locatie)",
        "CREATE TABLE IF NOT EXISTS observatii_predefinite (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            text_observatie TEXT NOT NULL DEFAULT '',
            ordine INTEGER NOT NULL DEFAULT 0,
            activ INTEGER NOT NULL DEFAULT 1,
            toate_produsele INTEGER NOT NULL DEFAULT 1
        )",
        "CREATE INDEX IF NOT EXISTS idx_observatii_predefinite_active_order
            ON observatii_predefinite(activ, ordine, id)",
        "CREATE TABLE IF NOT EXISTS atribuiri_observatii_produse (
            id_observatie INTEGER NOT NULL,
            cod_produs INTEGER NOT NULL,
            PRIMARY KEY (id_observatie, cod_produs)
        )",
        "CREATE INDEX IF NOT EXISTS idx_atribuiri_observatii_produs
            ON atribuiri_observatii_produse(cod_produs)",
        "CREATE TABLE IF NOT EXISTS gestiuni (
            id_gestiune INTEGER PRIMARY KEY,
            denumire_gestiune TEXT DEFAULT ''
        )",
        "CREATE TABLE IF NOT EXISTS cote_tva (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cota REAL DEFAULT 0,
            dep_casa INTEGER DEFAULT 0
        )",
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_cote_tva_cota ON cote_tva(cota)",
        "CREATE TABLE IF NOT EXISTS coduri_casa_tva (
            id INTEGER PRIMARY KEY,
            cota_tva INTEGER NOT NULL DEFAULT 0,
            cod_listare_cota_casa INTEGER NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS offline_reference_sync_runtime (
            id INTEGER PRIMARY KEY CHECK(id = 1),
            vat_mirrored INTEGER NOT NULL DEFAULT 0,
            last_sync_at TEXT DEFAULT NULL
        )",
        "INSERT OR IGNORE INTO offline_reference_sync_runtime(id, vat_mirrored) VALUES(1, 0)",
        "CREATE TABLE IF NOT EXISTS produse_servicii (
            cod_produs INTEGER PRIMARY KEY,
            cod_bare TEXT DEFAULT '',
            nume TEXT DEFAULT '',
            nume_site TEXT DEFAULT '',
            nume_en TEXT DEFAULT '',
            descriere TEXT DEFAULT '',
            descriere_site TEXT DEFAULT '',
            descriere_en TEXT DEFAULT '',
            um TEXT DEFAULT 'BUC',
            pret_cu_tva REAL DEFAULT 0,
            pret_achizitie REAL DEFAULT 0,
            pret_site REAL DEFAULT 0,
            cota_tva REAL DEFAULT 0,
            id_categorie INTEGER DEFAULT 0,
            id_gestiune INTEGER DEFAULT 0,
            activ INTEGER DEFAULT 1,
            produs_activ_site INTEGER DEFAULT 0,
            stoc_status_site TEXT DEFAULT '',
            woo_product_id INTEGER DEFAULT NULL,
            se_vinde INTEGER DEFAULT 1,
            departament TEXT DEFAULT '',
            dep_casa_marcat INTEGER DEFAULT 1,
            tip TEXT DEFAULT 'produs',
            fel_mancare INTEGER DEFAULT 0,
            ask_obs INTEGER DEFAULT 0,
            imagine TEXT DEFAULT '',
            imagine_site TEXT DEFAULT '',
            stoc_critic REAL DEFAULT 0,
            nc8 TEXT DEFAULT '',
            infopret_kg REAL DEFAULT 0,
            consumabil_de_personal INTEGER DEFAULT 0,
            sgr INTEGER DEFAULT 0,
            sgr_pet INTEGER DEFAULT 0,
            sgr_alumin INTEGER DEFAULT 0,
            sgr_sticla INTEGER DEFAULT 0
        )",
        "CREATE INDEX IF NOT EXISTS idx_produse_categorie ON produse_servicii(id_categorie)",
        "CREATE TABLE IF NOT EXISTS mapare_woo_pos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            woo_product_id INTEGER NOT NULL,
            woo_tip TEXT NOT NULL DEFAULT 'simple',
            cod_produs INTEGER NOT NULL,
            nume_woo TEXT NOT NULL DEFAULT '',
            nume_pos TEXT NOT NULL DEFAULT '',
            metoda_mapare TEXT NOT NULL DEFAULT 'manual',
            activ INTEGER NOT NULL DEFAULT 1,
            observatii_interne TEXT DEFAULT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_mapare_woo_pos_product ON mapare_woo_pos(woo_product_id)",
        "CREATE INDEX IF NOT EXISTS idx_mapare_woo_pos_cod_produs ON mapare_woo_pos(cod_produs)",
        "CREATE INDEX IF NOT EXISTS idx_mapare_woo_pos_activ ON mapare_woo_pos(activ)",
        "CREATE TABLE IF NOT EXISTS woo_order_imports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            woo_order_id INTEGER NOT NULL,
            woo_order_number TEXT NOT NULL DEFAULT '',
            note_nrbon INTEGER NOT NULL DEFAULT 0,
            locatie INTEGER NOT NULL DEFAULT 0,
            imported_by INTEGER NOT NULL DEFAULT 0,
            imported_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            payload_hash TEXT DEFAULT NULL,
            listat_auto_de_pe_site INTEGER NOT NULL DEFAULT 0
        )",
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_woo_order_imports_order_location ON woo_order_imports(woo_order_id, locatie)",
        "CREATE INDEX IF NOT EXISTS idx_woo_order_imports_note ON woo_order_imports(note_nrbon)",
        "CREATE INDEX IF NOT EXISTS idx_woo_order_imports_date ON woo_order_imports(imported_at)",
        "CREATE TABLE IF NOT EXISTS date_firma (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            den_ent TEXT DEFAULT '',
            cod_fiscal TEXT DEFAULT '',
            denumire_firma TEXT DEFAULT '',
            pseudonim_firma TEXT DEFAULT '',
            cui TEXT DEFAULT '',
            nr_reg_com TEXT DEFAULT '',
            sediu TEXT DEFAULT '',
            judet TEXT DEFAULT '',
            localitate TEXT DEFAULT '',
            banca TEXT DEFAULT '',
            cont_banca TEXT DEFAULT '',
            cap_soc REAL DEFAULT 0,
            numar_zile_scadenta INTEGER DEFAULT 0,
            serie_factura_comenzi TEXT DEFAULT '',
            serie_factura_implicita TEXT DEFAULT '',
            nr_zile_activ_storn INTEGER DEFAULT 0,
            logo TEXT DEFAULT '',
            cota_tva_predefinita INTEGER DEFAULT 0,
            url TEXT DEFAULT '',
            email TEXT DEFAULT '',
            telefon TEXT DEFAULT '',
            tva INTEGER DEFAULT 0,
            token_anaf TEXT DEFAULT '',
            metoda_plata_implicita TEXT DEFAULT '',
            serie_stornare TEXT DEFAULT '',
            text_subsol_factura TEXT DEFAULT '',
            serie_casa_marcat TEXT DEFAULT '',
            nui INTEGER DEFAULT 0,
            serie_memorie_fiscala TEXT DEFAULT '',
            mod_listare TEXT DEFAULT 'simplu',
            conducator_entitate TEXT DEFAULT '',
            vanzare_sub_stoc INTEGER DEFAULT 1,
            ajustare_adaos INTEGER DEFAULT 0,
            adresa TEXT DEFAULT ''
        )",
        "CREATE TABLE IF NOT EXISTS note (
            nrbon INTEGER PRIMARY KEY,
            serie TEXT DEFAULT '',
            operator INTEGER DEFAULT 0,
            locatie INTEGER DEFAULT 0,
            cod_masa INTEGER DEFAULT 0,
            data_deschidere TEXT DEFAULT CURRENT_TIMESTAMP,
            data_bon TEXT DEFAULT (date('now','localtime')),
            ora_bon TEXT DEFAULT (time('now','localtime')),
            status TEXT DEFAULT 'S',
            valoare_vanzare_cu_tva REAL DEFAULT 0,
            tva_colectata REAL DEFAULT 0,
            discount REAL DEFAULT 0,
            numerar REAL DEFAULT 0,
            card REAL DEFAULT 0,
            tichete REAL DEFAULT 0,
            protocol REAL DEFAULT 0,
            glovo REAL DEFAULT 0,
            virament_bancar REAL DEFAULT 0,
            platit_din_sold REAL DEFAULT 0,
            rest REAL DEFAULT 0,
            cif_client TEXT DEFAULT '',
            tableta INTEGER DEFAULT 0,
            listat_nota_plata INTEGER DEFAULT 0,
            fiscalizat INTEGER DEFAULT 0,
            cod_inchidere INTEGER DEFAULT 0,
            nr_raport_z INTEGER DEFAULT 0,
            serie_casa_marcat TEXT DEFAULT '',
            nui INTEGER DEFAULT 0,
            serie_memorie_fiscala TEXT DEFAULT '',
            camera_nota TEXT DEFAULT ''
        )",
        "CREATE INDEX IF NOT EXISTS idx_note_status_locatie ON note(status, locatie)",
        "CREATE INDEX IF NOT EXISTS idx_note_masa_status ON note(cod_masa, status)",
        "CREATE TABLE IF NOT EXISTS det_note (
            id_vanz INTEGER PRIMARY KEY AUTOINCREMENT,
            nr_bon INTEGER DEFAULT 0,
            cod_p INTEGER DEFAULT 0,
            nume_produs TEXT DEFAULT '',
            cantitate REAL DEFAULT 0,
            cota_tva REAL DEFAULT 0,
            tva_col REAL DEFAULT 0,
            pret_vanzare REAL DEFAULT 0,
            valoare_vanzare REAL DEFAULT 0,
            valoare_vanzare_cu_tva REAL DEFAULT 0,
            discount REAL DEFAULT 0,
            pachet INTEGER DEFAULT 0,
            preparat INTEGER DEFAULT 0,
            t_list INTEGER DEFAULT 0,
            cod_meniu INTEGER DEFAULT 0,
            preluat_osp INTEGER DEFAULT 0,
            prioritate INTEGER DEFAULT 0,
            cod_meniu_pers INTEGER DEFAULT 0,
            meniu_instance_id INTEGER DEFAULT 0,
            meniu_instance_qty REAL DEFAULT 1,
            importat_din_site INTEGER DEFAULT NULL,
            departament_listare TEXT DEFAULT NULL,
            observatie_produs TEXT DEFAULT '',
            data TEXT DEFAULT (date('now','localtime')),
            ora TEXT DEFAULT (time('now','localtime'))
        )",
        "CREATE INDEX IF NOT EXISTS idx_det_note_nr_bon ON det_note(nr_bon)",
        "CREATE TABLE IF NOT EXISTS com_tableta (
            nrbon INTEGER PRIMARY KEY,
            serie TEXT DEFAULT '',
            data_bon TEXT DEFAULT (date('now','localtime')),
            ora_bon TEXT DEFAULT (time('now','localtime')),
            valoare_vanzare_cu_tva REAL DEFAULT 0,
            tva_colectata REAL DEFAULT 0,
            discount REAL DEFAULT 0,
            operator INTEGER DEFAULT 0,
            numerar REAL DEFAULT 0,
            card REAL DEFAULT 0,
            tichete REAL DEFAULT 0,
            rest REAL DEFAULT 0,
            protocol REAL DEFAULT 0,
            glovo REAL DEFAULT 0,
            virament_bancar REAL DEFAULT 0,
            cif_client TEXT DEFAULT '',
            cod_masa INTEGER DEFAULT 0,
            stare TEXT DEFAULT 'NEFINALIZATA',
            status TEXT DEFAULT 'N',
            cod_inchidere INTEGER DEFAULT 0,
            tableta INTEGER DEFAULT 1,
            locatie INTEGER DEFAULT 0,
            nr_raport_z INTEGER DEFAULT 0,
            data_deschidere TEXT DEFAULT CURRENT_TIMESTAMP,
            listat_nota_plata INTEGER DEFAULT 0,
            owner_operator_id INTEGER DEFAULT 0,
            owner_operator_name TEXT DEFAULT '',
            payload_hash TEXT DEFAULT '',
            fetched_at TEXT DEFAULT CURRENT_TIMESTAMP,
            imported_note_nrbon INTEGER DEFAULT 0,
            imported_at TEXT DEFAULT NULL,
            online_ack_status TEXT DEFAULT 'not_ready',
            online_ack_attempts INTEGER DEFAULT 0,
            online_ack_error TEXT DEFAULT '',
            online_acknowledged_at TEXT DEFAULT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_com_tableta_stare_operator ON com_tableta(stare, operator)",
        "CREATE INDEX IF NOT EXISTS idx_com_tableta_status_operator ON com_tableta(status, operator)",
        "CREATE TABLE IF NOT EXISTS det_com_tableta (
            id_vanz INTEGER PRIMARY KEY AUTOINCREMENT,
            nr_bon INTEGER DEFAULT 0,
            cod_p INTEGER DEFAULT 0,
            nume_produs TEXT DEFAULT '',
            cantitate REAL DEFAULT 0,
            cota_tva REAL DEFAULT 0,
            tva_col REAL DEFAULT 0,
            pret_vanzare REAL DEFAULT 0,
            valoare_vanzare REAL DEFAULT 0,
            valoare_vanzare_cu_tva REAL DEFAULT 0,
            discount REAL DEFAULT 0,
            pachet INTEGER DEFAULT 0,
            preparat INTEGER DEFAULT 0,
            t_list INTEGER DEFAULT 0,
            data TEXT DEFAULT (date('now','localtime')),
            ora TEXT DEFAULT (time('now','localtime')),
            cod_meniu INTEGER DEFAULT 0,
            observatie_produs TEXT DEFAULT '',
            preluat_osp INTEGER DEFAULT 0,
            prioritate INTEGER DEFAULT 0,
            online_id_vanz INTEGER DEFAULT 0,
            departament_listare TEXT DEFAULT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_det_com_tableta_nr_bon ON det_com_tableta(nr_bon)",
        "CREATE TABLE IF NOT EXISTS offline_tablet_sync_runtime (
            id INTEGER PRIMARY KEY CHECK(id = 1),
            last_pull_at TEXT DEFAULT NULL,
            last_pull_success_at TEXT DEFAULT NULL,
            last_ack_at TEXT DEFAULT NULL,
            last_ack_success_at TEXT DEFAULT NULL,
            last_error TEXT DEFAULT '',
            last_orders_received INTEGER DEFAULT 0,
            last_orders_inserted INTEGER DEFAULT 0,
            last_orders_updated INTEGER DEFAULT 0,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "INSERT OR IGNORE INTO offline_tablet_sync_runtime(id) VALUES(1)",
        "CREATE TABLE IF NOT EXISTS offline_tablet_sync_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT '',
            data_ora TEXT DEFAULT CURRENT_TIMESTAMP,
            received_count INTEGER DEFAULT 0,
            inserted_count INTEGER DEFAULT 0,
            updated_count INTEGER DEFAULT 0,
            acknowledged_count INTEGER DEFAULT 0,
            http_code INTEGER DEFAULT 0,
            message TEXT DEFAULT ''
        )",
        "CREATE INDEX IF NOT EXISTS idx_offline_tablet_sync_logs_data ON offline_tablet_sync_logs(data_ora)",
        "CREATE TABLE IF NOT EXISTS retete (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cod_p INTEGER DEFAULT 0,
            cod_mat INTEGER DEFAULT 0,
            cant_folos REAL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS miscari (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            data TEXT DEFAULT (date('now','localtime')),
            tip_miscare TEXT DEFAULT '',
            fel_doc TEXT DEFAULT '',
            nr_doc INTEGER DEFAULT 0,
            nr_nota INTEGER DEFAULT 0,
            cod_p INTEGER DEFAULT 0,
            denumire_produs TEXT DEFAULT '',
            cantitate_misc REAL DEFAULT 0,
            pu REAL DEFAULT 0,
            pret_vanzare REAL DEFAULT 0,
            valoare_achizitie REAL DEFAULT 0,
            valoare_vanzare REAL DEFAULT 0,
            cota_tva INTEGER DEFAULT NULL,
            diminueaza_pe INTEGER DEFAULT 0,
            produs_obtinut INTEGER DEFAULT 0,
            nume_produs_obtinut TEXT DEFAULT '',
            ramas REAL DEFAULT 0,
            nr_nir TEXT DEFAULT '',
            id_doc INTEGER DEFAULT 0,
            gestiune TEXT DEFAULT '',
            id_achiz INTEGER DEFAULT 0,
            id_vanz_fact INTEGER DEFAULT 0,
            id_detaliu_deviz INTEGER DEFAULT NULL,
            id_rand_bon_consum_manual INTEGER DEFAULT 0,
            id_rand_bon_consum_productie INTEGER DEFAULT 0,
            id_rand_proces_verbal_inventar INTEGER DEFAULT 0,
            id_detaliu_bon_transfer INTEGER DEFAULT NULL,
            id_retur INTEGER DEFAULT 0,
            id_rand_pv_deteriorare INTEGER DEFAULT 0,
            cod_locatie INTEGER DEFAULT 0,
            ora_miscarii TEXT DEFAULT (time('now','localtime')),
            nr_raport_z INTEGER DEFAULT 0
        )",
        "CREATE INDEX IF NOT EXISTS idx_miscari_doc ON miscari(fel_doc, nr_doc)",
        "CREATE TABLE IF NOT EXISTS bonuri_casa_marcat (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            data TEXT DEFAULT (date('now','localtime')),
            ora TEXT DEFAULT (time('now','localtime')),
            continut_bon TEXT DEFAULT '',
            de_trimis_la_casa_marcat INTEGER DEFAULT 1,
            nrbon INTEGER DEFAULT 0,
            locatie INTEGER DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS discounturi_acordate (
            id_discount INTEGER PRIMARY KEY AUTOINCREMENT,
            id_vanz INTEGER DEFAULT 0,
            id_operator INTEGER DEFAULT 0,
            tip_discount TEXT DEFAULT '',
            valoare_procent REAL DEFAULT 0,
            valoare_discount_ron REAL DEFAULT 0,
            pret_unitar_initial REAL DEFAULT 0,
            pret_unitar_final REAL DEFAULT 0,
            id_operatiune_globala TEXT DEFAULT '',
            data TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE INDEX IF NOT EXISTS idx_discounturi_id_vanz ON discounturi_acordate(id_vanz)",
        "CREATE TABLE IF NOT EXISTS inchideri_r_12 (
            id_inch INTEGER PRIMARY KEY AUTOINCREMENT,
            cod_inchidere INTEGER DEFAULT 0,
            operator INTEGER DEFAULT 0,
            valoare_cu_tva REAL DEFAULT 0,
            tva_colectata REAL DEFAULT 0,
            data_inchiderii TEXT DEFAULT '',
            ora_inchiderii TEXT DEFAULT '',
            locatie INTEGER DEFAULT 0,
            nr_raport_z INTEGER DEFAULT 0,
            serie_casa_marcat TEXT DEFAULT '',
            nui INTEGER DEFAULT 0,
            serie_memorie_fiscala TEXT DEFAULT '',
            totaluri_plata_json TEXT DEFAULT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_inchideri_r_12_locatie_cod ON inchideri_r_12(locatie, cod_inchidere)",
        "CREATE INDEX IF NOT EXISTS idx_inchideri_r_12_locatie_z ON inchideri_r_12(locatie, nr_raport_z)",
        "CREATE TABLE IF NOT EXISTS offline_sync_exported (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            export_id TEXT NOT NULL,
            source_table TEXT NOT NULL,
            source_pk TEXT NOT NULL,
            cod_locatie INTEGER DEFAULT 0,
            original_id TEXT DEFAULT '',
            sync_id TEXT NOT NULL,
            payload_hash TEXT DEFAULT '',
            exported_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(source_table, source_pk, cod_locatie)
        )",
        "CREATE INDEX IF NOT EXISTS idx_offline_sync_exported_export ON offline_sync_exported(export_id)",
        "CREATE TABLE IF NOT EXISTS offline_sync_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            export_id TEXT DEFAULT '',
            data_ora TEXT DEFAULT CURRENT_TIMESTAMP,
            utilizator_id INTEGER DEFAULT 0,
            utilizator_nume TEXT DEFAULT '',
            cod_locatie INTEGER DEFAULT 0,
            note_count INTEGER DEFAULT 0,
            det_note_count INTEGER DEFAULT 0,
            inchideri_count INTEGER DEFAULT 0,
            rapoarte_z_count INTEGER DEFAULT 0,
            miscari_count INTEGER DEFAULT 0,
            discounturi_count INTEGER DEFAULT 0,
            status TEXT DEFAULT '',
            fisier_export TEXT DEFAULT '',
            payload_hash TEXT DEFAULT '',
            erori TEXT DEFAULT '',
            declansare TEXT DEFAULT 'manual',
            durata_ms INTEGER DEFAULT 0,
            online_status TEXT DEFAULT '',
            online_http_code INTEGER DEFAULT 0,
            online_message TEXT DEFAULT '',
            online_inserted_json TEXT DEFAULT '',
            online_duplicates_json TEXT DEFAULT '',
            online_updated_json TEXT DEFAULT ''
        )",
        "CREATE INDEX IF NOT EXISTS idx_offline_sync_logs_data ON offline_sync_logs(data_ora)",
        "CREATE TABLE IF NOT EXISTS offline_sync_outbox (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_uuid TEXT NOT NULL UNIQUE,
            event_type TEXT NOT NULL,
            aggregate_type TEXT NOT NULL,
            aggregate_id TEXT NOT NULL,
            cod_locatie INTEGER NOT NULL DEFAULT 0,
            payload_json TEXT NOT NULL,
            payload_sha256 TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            attempts INTEGER NOT NULL DEFAULT 0,
            next_attempt_at TEXT DEFAULT NULL,
            locked_at TEXT DEFAULT NULL,
            last_http_code INTEGER DEFAULT 0,
            last_error TEXT DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at TEXT DEFAULT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_offline_sync_outbox_due ON offline_sync_outbox(status, next_attempt_at, id)",
        "CREATE INDEX IF NOT EXISTS idx_offline_sync_outbox_entity ON offline_sync_outbox(aggregate_type, aggregate_id, id)",
        "CREATE TABLE IF NOT EXISTS offline_sync_entity_state (
            entity_type TEXT NOT NULL,
            entity_id TEXT NOT NULL,
            payload_sha256 TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(entity_type, entity_id)
        )",
        "CREATE TABLE IF NOT EXISTS offline_sync_runtime (
            id INTEGER PRIMARY KEY CHECK(id = 1),
            last_tick_at TEXT DEFAULT NULL,
            last_success_at TEXT DEFAULT NULL,
            last_error TEXT DEFAULT '',
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "INSERT OR IGNORE INTO offline_sync_runtime(id) VALUES(1)",
        "CREATE TABLE IF NOT EXISTS offline_license_runtime (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            last_seen_epoch INTEGER NOT NULL DEFAULT 0,
            last_server_epoch INTEGER NOT NULL DEFAULT 0,
            last_attempt_epoch INTEGER NOT NULL DEFAULT 0,
            last_success_epoch INTEGER NOT NULL DEFAULT 0,
            last_error TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )",
        "INSERT OR IGNORE INTO offline_license_runtime(id) VALUES(1)",
        "CREATE TABLE IF NOT EXISTS offline_runtime_context (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            cod_locatie INTEGER DEFAULT 0,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS eliberari_mese (
            id_eliberare INTEGER PRIMARY KEY AUTOINCREMENT,
            nrbon INTEGER DEFAULT 0,
            motiv TEXT DEFAULT '',
            sters_de INTEGER DEFAULT 0,
            detalii_eliberare TEXT DEFAULT '',
            data_eliberare TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS incasari_bratari (
            id_incasare INTEGER PRIMARY KEY AUTOINCREMENT,
            incasare_id INTEGER DEFAULT 0,
            id_vanz INTEGER DEFAULT 0,
            nr_bon INTEGER DEFAULT 0,
            cod_masa INTEGER DEFAULT 0,
            cod_p INTEGER DEFAULT 0,
            nume_produs TEXT DEFAULT '',
            cantitate REAL DEFAULT 0,
            cota_tva REAL DEFAULT 0,
            tva_col REAL DEFAULT 0,
            pret_vanzare REAL DEFAULT 0,
            valoare_vanzare REAL DEFAULT 0,
            valoare_vanzare_cu_tva REAL DEFAULT 0,
            discount REAL DEFAULT 0,
            pachet INTEGER DEFAULT 0,
            preparat INTEGER DEFAULT 0,
            t_list INTEGER DEFAULT 0,
            data TEXT DEFAULT '',
            ora TEXT DEFAULT '',
            operator INTEGER DEFAULT 0,
            bratara_inchisa INTEGER DEFAULT 0,
            cod_meniu INTEGER DEFAULT 0,
            observatie_produs TEXT DEFAULT '',
            preluat_osp INTEGER DEFAULT 0,
            prioritate INTEGER DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS rapoarte_z (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nr_raport_z INTEGER DEFAULT 0,
            cod_locatie INTEGER DEFAULT 0,
            serie_casa_marcat TEXT DEFAULT '',
            nui INTEGER DEFAULT 0,
            serie_memorie_fiscala TEXT DEFAULT '',
            numerar REAL DEFAULT 0,
            card REAL DEFAULT 0,
            credit REAL DEFAULT 0,
            tichete_masa REAL DEFAULT 0,
            tichete_valorice REAL DEFAULT 0,
            plata_moderna REAL DEFAULT 0,
            avans_in_numerar REAL DEFAULT 0,
            alte_metode REAL DEFAULT 0,
            data_ora_raport_z TEXT DEFAULT CURRENT_TIMESTAMP,
            data_raport TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE INDEX IF NOT EXISTS idx_rapoarte_z_cod_locatie ON rapoarte_z(cod_locatie)",
        "CREATE TABLE IF NOT EXISTS utilizatori (
            id_utilizator INTEGER PRIMARY KEY,
            rang TEXT DEFAULT '',
            adresa_de_email TEXT DEFAULT ''
        )",
        "INSERT INTO setari_platforma (id, cu_imprimanta, autologin_restaurant)
            SELECT 1, 1, 0
            WHERE NOT EXISTS (SELECT 1 FROM setari_platforma)",
        "INSERT INTO cote_tva (cota, dep_casa)
            SELECT 21, 1 WHERE NOT EXISTS (SELECT 1 FROM cote_tva WHERE cota = 21)
              AND COALESCE((SELECT vat_mirrored FROM offline_reference_sync_runtime WHERE id = 1), 0) = 0",
        "INSERT INTO cote_tva (cota, dep_casa)
            SELECT 11, 2 WHERE NOT EXISTS (SELECT 1 FROM cote_tva WHERE cota = 11)
              AND COALESCE((SELECT vat_mirrored FROM offline_reference_sync_runtime WHERE id = 1), 0) = 0",
        "INSERT INTO cote_tva (cota, dep_casa)
            SELECT 19, 1 WHERE NOT EXISTS (SELECT 1 FROM cote_tva WHERE cota = 19)
              AND COALESCE((SELECT vat_mirrored FROM offline_reference_sync_runtime WHERE id = 1), 0) = 0",
        "INSERT INTO cote_tva (cota, dep_casa)
            SELECT 9, 2 WHERE NOT EXISTS (SELECT 1 FROM cote_tva WHERE cota = 9)
              AND COALESCE((SELECT vat_mirrored FROM offline_reference_sync_runtime WHERE id = 1), 0) = 0",
        "INSERT INTO cote_tva (cota, dep_casa)
            SELECT 5, 3 WHERE NOT EXISTS (SELECT 1 FROM cote_tva WHERE cota = 5)
              AND COALESCE((SELECT vat_mirrored FROM offline_reference_sync_runtime WHERE id = 1), 0) = 0",
        "INSERT INTO cote_tva (cota, dep_casa)
            SELECT 0, 4 WHERE NOT EXISTS (SELECT 1 FROM cote_tva WHERE cota = 0)
              AND COALESCE((SELECT vat_mirrored FROM offline_reference_sync_runtime WHERE id = 1), 0) = 0"
    ];
}

function restaurant_sqlite_apply_schema(PDO $pdo): void
{
    foreach (restaurant_sqlite_schema_statements() as $sql) {
        $pdo->exec($sql);
    }

    restaurant_sqlite_ensure_miscari_schema($pdo);
    restaurant_sqlite_ensure_columns($pdo);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_com_tableta_owner_state ON com_tableta(owner_operator_id, stare, locatie)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_com_tableta_ack ON com_tableta(online_ack_status, stare)');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_det_com_tableta_online_row ON det_com_tableta(nr_bon, online_id_vanz) WHERE online_id_vanz > 0');
    restaurant_sqlite_ensure_cod_locatie_columns($pdo);
    restaurant_sqlite_backfill_cod_locatie_values($pdo);
    restaurant_sqlite_ensure_raport_z_identity_index($pdo);
    restaurant_sqlite_ensure_cod_locatie_triggers($pdo);
}

function restaurant_sqlite_quote_identifier(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function restaurant_sqlite_table_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('PRAGMA table_info(' . restaurant_sqlite_quote_identifier($table) . ')');
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[(string)$row['name']] = true;
    }
    return $columns;
}

function restaurant_sqlite_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sqlite_master
        WHERE type = 'table'
          AND name = :table
    ");
    $stmt->execute([':table' => $table]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function restaurant_sqlite_raport_z_current_identification(PDO $pdo, int $codLocatie): array
{
    $dateFirma = restaurant_sqlite_table_exists($pdo, 'date_firma')
        ? ($pdo->query('SELECT * FROM date_firma LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [])
        : [];

    $locMese = [];
    if (restaurant_sqlite_table_exists($pdo, 'loc_mese_12')) {
        $locColumns = restaurant_sqlite_table_columns($pdo, 'loc_mese_12');
        $where = '';
        $params = [];
        if (isset($locColumns['cod_locatie'])) {
            $where = ' WHERE cod_locatie = ?';
            $params[] = $codLocatie;
        } elseif (isset($locColumns['locatie'])) {
            $where = ' WHERE locatie = ?';
            $params[] = $codLocatie;
        }
        $stmt = $pdo->prepare('SELECT * FROM loc_mese_12' . $where . ' LIMIT 1');
        $stmt->execute($params);
        $locMese = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $series = trim((string)($dateFirma['serie_casa_marcat'] ?? ''));
    if ($series === '') {
        $series = trim((string)($locMese['serie_casa_marcat'] ?? ''));
    }

    $nui = (int)($dateFirma['nui'] ?? 0);
    if ($nui <= 0) {
        $nui = max(0, (int)($locMese['nui'] ?? 0));
    }

    $memory = trim((string)($dateFirma['serie_memorie_fiscala'] ?? ''));
    if ($memory === '' || $memory === '0') {
        $memory = trim((string)($locMese['serie_memorie_fiscala'] ?? ''));
    }
    if ($memory === '0') {
        $memory = '';
    }

    return [
        'serie_casa_marcat' => $series,
        'nui' => $nui,
        'serie_memorie_fiscala' => $memory,
    ];
}

function restaurant_sqlite_raport_z_next_number(PDO $pdo, int $codLocatie, int $nui = -1, string $memory = ''): int
{
    $identity = restaurant_sqlite_raport_z_current_identification($pdo, $codLocatie);
    if ($nui < 0) {
        $nui = $identity['nui'];
    }
    if ($memory === '') {
        $memory = $identity['serie_memorie_fiscala'];
    }

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(nr_raport_z), 0) + 1
        FROM rapoarte_z
        WHERE cod_locatie = ?
          AND COALESCE(serie_casa_marcat, '') = ?
          AND COALESCE(nui, 0) = ?
          AND COALESCE(serie_memorie_fiscala, '') = ?");
    $stmt->execute([$codLocatie, $identity['serie_casa_marcat'], $nui, $memory]);
    return max(1, (int)$stmt->fetchColumn());
}

function restaurant_sqlite_ensure_raport_z_identity_index(PDO $pdo): void
{
    if (!restaurant_sqlite_table_exists($pdo, 'rapoarte_z')) {
        return;
    }

    try {
        $duplicates = (int)$pdo->query("SELECT COUNT(*) FROM (
            SELECT cod_locatie,
                   COALESCE(serie_casa_marcat, ''),
                   COALESCE(nui, 0),
                   COALESCE(serie_memorie_fiscala, ''),
                   nr_raport_z
            FROM rapoarte_z
            GROUP BY cod_locatie,
                     COALESCE(serie_casa_marcat, ''),
                     COALESCE(nui, 0),
                     COALESCE(serie_memorie_fiscala, ''),
                     nr_raport_z
            HAVING COUNT(*) > 1
        )")->fetchColumn();
        if ($duplicates > 0) {
            error_log('Indexul fiscal extins pentru rapoarte_z nu a fost creat deoarece exista duplicate istorice.');
            return;
        }

        $indexes = $pdo->query("PRAGMA index_list('rapoarte_z')")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indexes as $index) {
            if (strtolower((string)($index['name'] ?? '')) === 'uq_rapoarte_z_offline_fiscal') {
                $pdo->exec('DROP INDEX IF EXISTS "uq_rapoarte_z_offline_fiscal"');
            }
        }

        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_rapoarte_z_offline_fiscal_identity
            ON rapoarte_z (cod_locatie, serie_casa_marcat, nui, serie_memorie_fiscala, nr_raport_z)');
    } catch (Throwable $e) {
        error_log('Nu s-a putut crea indexul fiscal extins pentru rapoarte_z: ' . $e->getMessage());
    }
}


/**
 * Păstrează minimum 20 de mese temporare pentru împărțirea notelor.
 * Funcția este idempotentă și completează doar mesele care lipsesc.
 */
function restaurant_sqlite_ensure_temporary_tables(PDO $pdo, int $codLocatie, int $minimum = 20): void
{
    if ($codLocatie <= 0 || $minimum <= 0 || !restaurant_sqlite_table_exists($pdo, 'mese')) {
        return;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM mese WHERE cod_locatie = ? AND categorie_masa = 'TEMPORARA'");
    $countStmt->execute([$codLocatie]);
    if ((int)$countStmt->fetchColumn() >= $minimum) {
        return;
    }

    $startedTransaction = false;
    try {
        if (!$pdo->inTransaction()) {
            $pdo->exec('BEGIN IMMEDIATE');
            $startedTransaction = true;
        }

        $countStmt->execute([$codLocatie]);
        $currentCount = (int)$countStmt->fetchColumn();
        if ($currentCount < $minimum) {
            $namesStmt = $pdo->prepare('SELECT nume_masa FROM mese WHERE cod_locatie = ?');
            $namesStmt->execute([$codLocatie]);
            $existingNames = [];
            foreach ($namesStmt->fetchAll(PDO::FETCH_COLUMN) as $existingName) {
                $existingNames[strtolower(trim((string)$existingName))] = true;
            }

            $nextCode = (int)$pdo->query('SELECT COALESCE(MAX(cod_masa), 0) + 1 FROM mese')->fetchColumn();
            $insertStmt = $pdo->prepare("
                INSERT INTO mese (
                    cod_masa, nume_masa, cod_locatie, stare, tip_masa, sold,
                    cod_bratara, date_posesor, categorie_masa, vandut_intrare,
                    masa_comenzi_online
                ) VALUES (?, ?, ?, 0, 'simpla', 0, '', '', 'TEMPORARA', 0, 0)
            ");

            $suffix = 1;
            while ($currentCount < $minimum) {
                $name = 'Masa Temporara ' . $suffix;
                $normalizedName = strtolower($name);
                $suffix++;
                if (isset($existingNames[$normalizedName])) {
                    continue;
                }

                $insertStmt->execute([$nextCode, $name, $codLocatie]);
                $existingNames[$normalizedName] = true;
                $nextCode++;
                $currentCount++;
            }
        }

        if ($startedTransaction) {
            $pdo->exec('COMMIT');
        }
    } catch (Throwable $e) {
        if ($startedTransaction) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (Throwable $rollbackError) {
                // Tranzacția poate fi deja închisă de SQLite după o eroare fatală de scriere.
            }
        }
        throw $e;
    }
}

function restaurant_sqlite_miscari_column_definitions(): array
{
    return [
        'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'data' => "TEXT DEFAULT (date('now','localtime'))",
        'tip_miscare' => "TEXT DEFAULT ''",
        'fel_doc' => "TEXT DEFAULT ''",
        'nr_doc' => 'INTEGER DEFAULT 0',
        'nr_nota' => 'INTEGER DEFAULT 0',
        'cod_p' => 'INTEGER DEFAULT 0',
        'denumire_produs' => "TEXT DEFAULT ''",
        'cantitate_misc' => 'REAL DEFAULT 0',
        'pu' => 'REAL DEFAULT 0',
        'pret_vanzare' => 'REAL DEFAULT 0',
        'valoare_achizitie' => 'REAL DEFAULT 0',
        'valoare_vanzare' => 'REAL DEFAULT 0',
        'cota_tva' => 'INTEGER DEFAULT NULL',
        'diminueaza_pe' => 'INTEGER DEFAULT 0',
        'produs_obtinut' => 'INTEGER DEFAULT 0',
        'nume_produs_obtinut' => "TEXT DEFAULT ''",
        'ramas' => 'REAL DEFAULT 0',
        'nr_nir' => "TEXT DEFAULT ''",
        'id_doc' => 'INTEGER DEFAULT 0',
        'gestiune' => "TEXT DEFAULT ''",
        'id_achiz' => 'INTEGER DEFAULT 0',
        'id_vanz_fact' => 'INTEGER DEFAULT 0',
        'id_detaliu_deviz' => 'INTEGER DEFAULT NULL',
        'id_rand_bon_consum_manual' => 'INTEGER DEFAULT 0',
        'id_rand_bon_consum_productie' => 'INTEGER DEFAULT 0',
        'id_rand_proces_verbal_inventar' => 'INTEGER DEFAULT 0',
        'id_detaliu_bon_transfer' => 'INTEGER DEFAULT NULL',
        'id_retur' => 'INTEGER DEFAULT 0',
        'id_rand_pv_deteriorare' => 'INTEGER DEFAULT 0',
        'cod_locatie' => 'INTEGER DEFAULT 0',
        'ora_miscarii' => "TEXT DEFAULT (time('now','localtime'))",
        'nr_raport_z' => 'INTEGER DEFAULT 0',
        'nui' => 'INTEGER DEFAULT 0',
        'serie_memorie_fiscala' => "TEXT DEFAULT ''",
    ];
}

function restaurant_sqlite_miscari_default_expression(string $column, array $existing): string
{
    if ($column === 'valoare_achizitie' && isset($existing['cantitate_misc'], $existing['pu'])) {
        return '(COALESCE("cantitate_misc", 0) * COALESCE("pu", 0))';
    }

    if ($column === 'valoare_vanzare' && isset($existing['cantitate_misc'], $existing['pret_vanzare'])) {
        return '(COALESCE("cantitate_misc", 0) * COALESCE("pret_vanzare", 0))';
    }

    if ($column === 'ramas' && isset($existing['cantitate_misc'])) {
        return 'COALESCE("cantitate_misc", 0)';
    }

    $defaults = [
        'data' => "date('now','localtime')",
        'tip_miscare' => "''",
        'fel_doc' => "''",
        'nr_doc' => '0',
        'nr_nota' => '0',
        'cod_p' => '0',
        'denumire_produs' => "''",
        'cantitate_misc' => '0',
        'pu' => '0',
        'pret_vanzare' => '0',
        'valoare_achizitie' => '0',
        'valoare_vanzare' => '0',
        'cota_tva' => 'NULL',
        'diminueaza_pe' => '0',
        'produs_obtinut' => '0',
        'nume_produs_obtinut' => "''",
        'ramas' => '0',
        'nr_nir' => "''",
        'id_doc' => '0',
        'gestiune' => "''",
        'id_achiz' => '0',
        'id_vanz_fact' => '0',
        'id_detaliu_deviz' => 'NULL',
        'id_rand_bon_consum_manual' => '0',
        'id_rand_bon_consum_productie' => '0',
        'id_rand_proces_verbal_inventar' => '0',
        'id_detaliu_bon_transfer' => 'NULL',
        'id_retur' => '0',
        'id_rand_pv_deteriorare' => '0',
        'cod_locatie' => '0',
        'ora_miscarii' => "time('now','localtime')",
        'nr_raport_z' => '0',
        'nui' => '0',
        'serie_memorie_fiscala' => "''",
    ];

    return $defaults[$column] ?? 'NULL';
}

function restaurant_sqlite_ensure_miscari_indexes(PDO $pdo): void
{
    $indexes = [
        'idx_miscari_doc' => ['fel_doc', 'nr_doc'],
        'idx_miscari_cod_p' => ['cod_p'],
        'idx_miscari_id_achiz' => ['id_achiz'],
        'idx_miscari_id_vanz_fact' => ['id_vanz_fact'],
        'idx_miscari_id_rand_bon_consum' => ['id_rand_bon_consum_manual'],
        'idx_miscari_id_rand_proces_verbal_inv' => ['id_rand_proces_verbal_inventar'],
        'idx_miscari_id_retur' => ['id_retur'],
        'idx_miscari_nr_nir' => ['nr_nir'],
        'idx_miscari_nr_doc' => ['nr_doc'],
        'idx_miscari_nr_nota' => ['nr_nota'],
        'idx_miscari_id_detaliu_deviz' => ['id_detaliu_deviz'],
        'idx_miscari_cod_locatie' => ['cod_locatie'],
    ];

    foreach ($indexes as $index => $columns) {
        $quotedIndex = restaurant_sqlite_quote_identifier($index);
        $quotedColumns = implode(', ', array_map('restaurant_sqlite_quote_identifier', $columns));
        $pdo->exec("CREATE INDEX IF NOT EXISTS {$quotedIndex} ON miscari({$quotedColumns})");
    }
}

function restaurant_sqlite_drop_table_triggers(PDO $pdo, string $table): void
{
    $stmt = $pdo->prepare("
        SELECT name
        FROM sqlite_master
        WHERE type = 'trigger'
          AND tbl_name = :table
    ");
    $stmt->execute([':table' => $table]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $trigger) {
        $pdo->exec('DROP TRIGGER IF EXISTS ' . restaurant_sqlite_quote_identifier((string)$trigger));
    }
}

function restaurant_sqlite_rebuild_miscari_table(PDO $pdo, array $existing): void
{
    $definitions = restaurant_sqlite_miscari_column_definitions();
    $newTable = 'miscari__new';
    $quotedNewTable = restaurant_sqlite_quote_identifier($newTable);
    $quotedOldTable = restaurant_sqlite_quote_identifier('miscari');
    $columnsSql = [];

    foreach ($definitions as $column => $definition) {
        $columnsSql[] = restaurant_sqlite_quote_identifier($column) . ' ' . $definition;
    }

    restaurant_sqlite_drop_table_triggers($pdo, 'miscari');
    $pdo->exec('DROP TABLE IF EXISTS ' . $quotedNewTable);
    $pdo->exec('CREATE TABLE ' . $quotedNewTable . ' (' . implode(', ', $columnsSql) . ')');

    $targetColumns = array_keys($definitions);
    $selectExpressions = [];
    foreach ($targetColumns as $column) {
        if ($column === 'id') {
            if (isset($existing['id'])) {
                $selectExpressions[] = restaurant_sqlite_quote_identifier('id');
            } elseif (isset($existing['id_miscare'])) {
                $selectExpressions[] = restaurant_sqlite_quote_identifier('id_miscare');
            } else {
                $selectExpressions[] = 'rowid';
            }
            continue;
        }

        if (isset($existing[$column])) {
            $selectExpressions[] = restaurant_sqlite_quote_identifier($column);
            continue;
        }

        $selectExpressions[] = restaurant_sqlite_miscari_default_expression($column, $existing);
    }

    $quotedTargetColumns = implode(', ', array_map('restaurant_sqlite_quote_identifier', $targetColumns));
    $pdo->exec("
        INSERT INTO {$quotedNewTable} ({$quotedTargetColumns})
        SELECT " . implode(', ', $selectExpressions) . "
        FROM {$quotedOldTable}
    ");
    $pdo->exec('DROP TABLE ' . $quotedOldTable);
    $pdo->exec('ALTER TABLE ' . $quotedNewTable . ' RENAME TO ' . $quotedOldTable);
    $pdo->exec("UPDATE sqlite_sequence SET seq = (SELECT COALESCE(MAX(id), 0) FROM miscari) WHERE name = 'miscari'");
}

function restaurant_sqlite_ensure_miscari_schema(PDO $pdo): void
{
    if (!restaurant_sqlite_table_exists($pdo, 'miscari')) {
        return;
    }

    $definitions = restaurant_sqlite_miscari_column_definitions();
    $existing = restaurant_sqlite_table_columns($pdo, 'miscari');
    $needsRebuild = isset($existing['id_miscare']) || !isset($existing['id']);

    if ($needsRebuild) {
        restaurant_sqlite_rebuild_miscari_table($pdo, $existing);
        restaurant_sqlite_ensure_miscari_indexes($pdo);
        return;
    }

    foreach ($definitions as $column => $definition) {
        if (isset($existing[$column])) {
            continue;
        }
        $pdo->exec('ALTER TABLE "miscari" ADD COLUMN ' . restaurant_sqlite_quote_identifier($column) . ' ' . $definition);
    }

    restaurant_sqlite_ensure_miscari_indexes($pdo);
}

function restaurant_sqlite_ensure_cod_locatie_columns(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT name
        FROM sqlite_master
        WHERE type = 'table'
          AND name NOT LIKE 'sqlite_%'
        ORDER BY name
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $table = (string)$table;
        $columns = restaurant_sqlite_table_columns($pdo, $table);
        $quotedTable = restaurant_sqlite_quote_identifier($table);

        if (!isset($columns['cod_locatie'])) {
            $pdo->exec('ALTER TABLE ' . $quotedTable . ' ADD COLUMN "cod_locatie" INTEGER DEFAULT 0');
            $columns['cod_locatie'] = true;
        }

        if (isset($columns['locatie'])) {
            $pdo->exec("
                UPDATE {$quotedTable}
                SET cod_locatie = locatie
                WHERE COALESCE(cod_locatie, 0) = 0
                  AND COALESCE(locatie, 0) <> 0
            ");
        }
    }
}

function restaurant_sqlite_trigger_name(string $table, string $suffix): string
{
    return 'trg_' . preg_replace('/[^A-Za-z0-9_]/', '_', $table) . '_' . $suffix;
}

function restaurant_sqlite_create_locatie_trigger(PDO $pdo, string $table): void
{
    $quotedTable = restaurant_sqlite_quote_identifier($table);
    $insertTrigger = restaurant_sqlite_quote_identifier(restaurant_sqlite_trigger_name($table, 'cod_locatie_ai'));
    $updateTrigger = restaurant_sqlite_quote_identifier(restaurant_sqlite_trigger_name($table, 'cod_locatie_au_locatie'));

    $pdo->exec("
        CREATE TRIGGER IF NOT EXISTS {$insertTrigger}
        AFTER INSERT ON {$quotedTable}
        WHEN COALESCE(NEW.cod_locatie, 0) = 0 AND COALESCE(NEW.locatie, 0) <> 0
        BEGIN
            UPDATE {$quotedTable}
            SET cod_locatie = NEW.locatie
            WHERE rowid = NEW.rowid;
        END
    ");

    $pdo->exec("
        CREATE TRIGGER IF NOT EXISTS {$updateTrigger}
        AFTER UPDATE OF locatie ON {$quotedTable}
        WHEN COALESCE(NEW.cod_locatie, 0) = 0 AND COALESCE(NEW.locatie, 0) <> 0
        BEGIN
            UPDATE {$quotedTable}
            SET cod_locatie = NEW.locatie
            WHERE rowid = NEW.rowid;
        END
    ");
}

function restaurant_sqlite_create_context_trigger(PDO $pdo, string $table): void
{
    $quotedTable = restaurant_sqlite_quote_identifier($table);
    $insertTrigger = restaurant_sqlite_quote_identifier(restaurant_sqlite_trigger_name($table, 'cod_locatie_context_ai'));

    $pdo->exec("
        CREATE TRIGGER IF NOT EXISTS {$insertTrigger}
        AFTER INSERT ON {$quotedTable}
        WHEN COALESCE(NEW.cod_locatie, 0) = 0
        BEGIN
            UPDATE {$quotedTable}
            SET cod_locatie = COALESCE(
                (SELECT NULLIF(cod_locatie, 0) FROM offline_runtime_context WHERE id = 1),
                cod_locatie
            )
            WHERE rowid = NEW.rowid;
        END
    ");
}

function restaurant_sqlite_context_trigger_excluded_tables(): array
{
    return [
        'det_note' => true,
        'det_com_tableta' => true,
        'discounturi_acordate' => true,
        'offline_runtime_context' => true,
    ];
}

function restaurant_sqlite_ensure_cod_locatie_triggers(PDO $pdo): void
{
    $contextExcluded = restaurant_sqlite_context_trigger_excluded_tables();
    $stmt = $pdo->query("
        SELECT name
        FROM sqlite_master
        WHERE type = 'table'
          AND name NOT LIKE 'sqlite_%'
        ORDER BY name
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $table = (string)$table;
        $columns = restaurant_sqlite_table_columns($pdo, $table);
        if (isset($columns['cod_locatie'], $columns['locatie'])) {
            restaurant_sqlite_create_locatie_trigger($pdo, $table);
            continue;
        }

        if (isset($columns['cod_locatie']) && !isset($contextExcluded[$table])) {
            restaurant_sqlite_create_context_trigger($pdo, $table);
        }
    }

    $pdo->exec("
        CREATE TRIGGER IF NOT EXISTS trg_det_note_cod_locatie_ai
        AFTER INSERT ON det_note
        WHEN COALESCE(NEW.cod_locatie, 0) = 0
        BEGIN
            UPDATE det_note
            SET cod_locatie = COALESCE(
                (SELECT NULLIF(COALESCE(n.cod_locatie, 0), 0) FROM note n WHERE n.nrbon = NEW.nr_bon LIMIT 1),
                (SELECT NULLIF(COALESCE(n.locatie, 0), 0) FROM note n WHERE n.nrbon = NEW.nr_bon LIMIT 1),
                0
            )
            WHERE rowid = NEW.rowid;
        END
    ");

    $pdo->exec("
        CREATE TRIGGER IF NOT EXISTS trg_det_note_cod_locatie_au_nr_bon
        AFTER UPDATE OF nr_bon ON det_note
        WHEN COALESCE(NEW.cod_locatie, 0) = 0
        BEGIN
            UPDATE det_note
            SET cod_locatie = COALESCE(
                (SELECT NULLIF(COALESCE(n.cod_locatie, 0), 0) FROM note n WHERE n.nrbon = NEW.nr_bon LIMIT 1),
                (SELECT NULLIF(COALESCE(n.locatie, 0), 0) FROM note n WHERE n.nrbon = NEW.nr_bon LIMIT 1),
                0
            )
            WHERE rowid = NEW.rowid;
        END
    ");

    $pdo->exec("
        CREATE TRIGGER IF NOT EXISTS trg_det_com_tableta_cod_locatie_ai
        AFTER INSERT ON det_com_tableta
        WHEN COALESCE(NEW.cod_locatie, 0) = 0
        BEGIN
            UPDATE det_com_tableta
            SET cod_locatie = COALESCE(
                (SELECT NULLIF(COALESCE(c.cod_locatie, 0), 0) FROM com_tableta c WHERE c.nrbon = NEW.nr_bon LIMIT 1),
                (SELECT NULLIF(COALESCE(c.locatie, 0), 0) FROM com_tableta c WHERE c.nrbon = NEW.nr_bon LIMIT 1),
                0
            )
            WHERE rowid = NEW.rowid;
        END
    ");

    $pdo->exec("
        CREATE TRIGGER IF NOT EXISTS trg_discounturi_cod_locatie_ai
        AFTER INSERT ON discounturi_acordate
        WHEN COALESCE(NEW.cod_locatie, 0) = 0
        BEGIN
            UPDATE discounturi_acordate
            SET cod_locatie = COALESCE(
                (SELECT NULLIF(COALESCE(d.cod_locatie, 0), 0) FROM det_note d WHERE d.id_vanz = NEW.id_vanz LIMIT 1),
                0
            )
            WHERE rowid = NEW.rowid;
        END
    ");
}

function restaurant_sqlite_backfill_cod_locatie_values(PDO $pdo, int $codLocatie = 0): void
{
    foreach (['det_note' => 'note', 'det_com_tableta' => 'com_tableta'] as $detailTable => $headerTable) {
        $detailColumns = restaurant_sqlite_table_columns($pdo, $detailTable);
        $headerColumns = restaurant_sqlite_table_columns($pdo, $headerTable);
        if (!isset($detailColumns['cod_locatie'], $detailColumns['nr_bon'], $headerColumns['nrbon'])) {
            continue;
        }

        $detail = restaurant_sqlite_quote_identifier($detailTable);
        $header = restaurant_sqlite_quote_identifier($headerTable);
        $codExpr = isset($headerColumns['cod_locatie'])
            ? "NULLIF(COALESCE(h.cod_locatie, 0), 0)"
            : 'NULL';
        $locatieExpr = isset($headerColumns['locatie'])
            ? "NULLIF(COALESCE(h.locatie, 0), 0)"
            : 'NULL';

        $pdo->exec("
            UPDATE {$detail}
            SET cod_locatie = COALESCE(
                (SELECT {$codExpr} FROM {$header} h WHERE h.nrbon = {$detail}.nr_bon LIMIT 1),
                (SELECT {$locatieExpr} FROM {$header} h WHERE h.nrbon = {$detail}.nr_bon LIMIT 1),
                cod_locatie
            )
            WHERE COALESCE(cod_locatie, 0) = 0
        ");
    }

    $discountColumns = restaurant_sqlite_table_columns($pdo, 'discounturi_acordate');
    $detColumns = restaurant_sqlite_table_columns($pdo, 'det_note');
    if (isset($discountColumns['cod_locatie'], $discountColumns['id_vanz'], $detColumns['id_vanz'], $detColumns['cod_locatie'])) {
        $pdo->exec("
            UPDATE discounturi_acordate
            SET cod_locatie = COALESCE(
                (SELECT NULLIF(COALESCE(d.cod_locatie, 0), 0) FROM det_note d WHERE d.id_vanz = discounturi_acordate.id_vanz LIMIT 1),
                cod_locatie
            )
            WHERE COALESCE(cod_locatie, 0) = 0
        ");
    }

    if ($codLocatie <= 0) {
        return;
    }

    $contextExcluded = restaurant_sqlite_context_trigger_excluded_tables();
    $stmt = $pdo->query("
        SELECT name
        FROM sqlite_master
        WHERE type = 'table'
          AND name NOT LIKE 'sqlite_%'
        ORDER BY name
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $table = (string)$table;
        if (isset($contextExcluded[$table])) {
            continue;
        }

        $columns = restaurant_sqlite_table_columns($pdo, $table);
        if (!isset($columns['cod_locatie']) || isset($columns['locatie'])) {
            continue;
        }

        $quotedTable = restaurant_sqlite_quote_identifier($table);
        $pdo->exec("
            UPDATE {$quotedTable}
            SET cod_locatie = {$codLocatie}
            WHERE COALESCE(cod_locatie, 0) = 0
        ");
    }
}

function restaurant_sqlite_set_cod_locatie_context(PDO $pdo, int $codLocatie): void
{
    if ($codLocatie <= 0) {
        return;
    }

    $update = $pdo->prepare("
        UPDATE offline_runtime_context
        SET cod_locatie = :cod_locatie,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = 1
    ");
    $update->execute([':cod_locatie' => $codLocatie]);

    if ($update->rowCount() === 0) {
        $insert = $pdo->prepare("
            INSERT INTO offline_runtime_context (id, cod_locatie, updated_at)
            VALUES (1, :cod_locatie, CURRENT_TIMESTAMP)
        ");
        $insert->execute([':cod_locatie' => $codLocatie]);
    }

    restaurant_sqlite_backfill_cod_locatie_values($pdo, $codLocatie);
}

function restaurant_sqlite_ensure_columns(PDO $pdo): void
{
    $tables = [
        'loc_mese_12' => [
            'cod_locatie' => 'INTEGER DEFAULT 0',
            'den_loc' => "TEXT DEFAULT ''",
            'denumire' => "TEXT DEFAULT ''",
            'serie_casa_marcat' => "TEXT DEFAULT ''",
            'nui' => 'INTEGER DEFAULT 0',
            'serie_memorie_fiscala' => "TEXT DEFAULT ''",
        ],
        'observatii_predefinite' => [
            'id' => 'INTEGER DEFAULT 0',
            'text_observatie' => "TEXT NOT NULL DEFAULT ''",
            'ordine' => 'INTEGER NOT NULL DEFAULT 0',
            'activ' => 'INTEGER NOT NULL DEFAULT 1',
            'toate_produsele' => 'INTEGER NOT NULL DEFAULT 1',
        ],
        'atribuiri_observatii_produse' => [
            'id_observatie' => 'INTEGER NOT NULL DEFAULT 0',
            'cod_produs' => 'INTEGER NOT NULL DEFAULT 0',
        ],
        'rapoarte_z' => [
            'identificator_offline' => 'TEXT DEFAULT NULL',
            'nr_raport_z' => 'INTEGER DEFAULT 0',
            'cod_locatie' => 'INTEGER DEFAULT 0',
            'serie_casa_marcat' => "TEXT DEFAULT ''",
            'nui' => 'INTEGER DEFAULT 0',
            'serie_memorie_fiscala' => "TEXT DEFAULT ''",
            'numerar' => 'REAL DEFAULT 0',
            'card' => 'REAL DEFAULT 0',
            'credit' => 'REAL DEFAULT 0',
            'tichete_masa' => 'REAL DEFAULT 0',
            'tichete_valorice' => 'REAL DEFAULT 0',
            'plata_moderna' => 'REAL DEFAULT 0',
            'avans_in_numerar' => 'REAL DEFAULT 0',
            'alte_metode' => 'REAL DEFAULT 0',
            'data_ora_raport_z' => "TEXT DEFAULT ''",
            'data_raport' => "TEXT DEFAULT ''",
        ],
        'inchideri_r_12' => [
            'identificator_offline' => 'TEXT DEFAULT NULL',
            'cod_inchidere' => 'INTEGER DEFAULT 0',
            'operator' => 'INTEGER DEFAULT 0',
            'valoare_cu_tva' => 'REAL DEFAULT 0',
            'tva_colectata' => 'REAL DEFAULT 0',
            'data_inchiderii' => "TEXT DEFAULT ''",
            'ora_inchiderii' => "TEXT DEFAULT ''",
            'locatie' => 'INTEGER DEFAULT 0',
            'nr_raport_z' => 'INTEGER DEFAULT 0',
            'serie_casa_marcat' => "TEXT DEFAULT ''",
            'nui' => 'INTEGER DEFAULT 0',
            'serie_memorie_fiscala' => "TEXT DEFAULT ''",
            'totaluri_plata_json' => 'TEXT DEFAULT NULL',
        ],
        'offline_sync_exported' => [
            'export_id' => "TEXT DEFAULT ''",
            'source_table' => "TEXT DEFAULT ''",
            'source_pk' => "TEXT DEFAULT ''",
            'cod_locatie' => 'INTEGER DEFAULT 0',
            'original_id' => "TEXT DEFAULT ''",
            'sync_id' => "TEXT DEFAULT ''",
            'payload_hash' => "TEXT DEFAULT ''",
            'exported_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
        ],
        'offline_sync_logs' => [
            'export_id' => "TEXT DEFAULT ''",
            'data_ora' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
            'utilizator_id' => 'INTEGER DEFAULT 0',
            'utilizator_nume' => "TEXT DEFAULT ''",
            'cod_locatie' => 'INTEGER DEFAULT 0',
            'note_count' => 'INTEGER DEFAULT 0',
            'det_note_count' => 'INTEGER DEFAULT 0',
            'inchideri_count' => 'INTEGER DEFAULT 0',
            'rapoarte_z_count' => 'INTEGER DEFAULT 0',
            'miscari_count' => 'INTEGER DEFAULT 0',
            'discounturi_count' => 'INTEGER DEFAULT 0',
            'status' => "TEXT DEFAULT ''",
            'fisier_export' => "TEXT DEFAULT ''",
            'payload_hash' => "TEXT DEFAULT ''",
            'erori' => "TEXT DEFAULT ''",
            'declansare' => "TEXT DEFAULT 'manual'",
            'durata_ms' => 'INTEGER DEFAULT 0',
            'online_status' => "TEXT DEFAULT ''",
            'online_http_code' => 'INTEGER DEFAULT 0',
            'online_message' => "TEXT DEFAULT ''",
            'online_inserted_json' => "TEXT DEFAULT ''",
            'online_duplicates_json' => "TEXT DEFAULT ''",
            'online_updated_json' => "TEXT DEFAULT ''",
        ],
        'offline_sync_outbox' => [
            'event_uuid' => "TEXT DEFAULT ''",
            'event_type' => "TEXT DEFAULT ''",
            'aggregate_type' => "TEXT DEFAULT ''",
            'aggregate_id' => "TEXT DEFAULT ''",
            'cod_locatie' => 'INTEGER DEFAULT 0',
            'payload_json' => "TEXT DEFAULT ''",
            'payload_sha256' => "TEXT DEFAULT ''",
            'status' => "TEXT DEFAULT 'pending'",
            'attempts' => 'INTEGER DEFAULT 0',
            'next_attempt_at' => 'TEXT DEFAULT NULL',
            'locked_at' => 'TEXT DEFAULT NULL',
            'last_http_code' => 'INTEGER DEFAULT 0',
            'last_error' => "TEXT DEFAULT ''",
            'created_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
            'sent_at' => 'TEXT DEFAULT NULL',
        ],
        'offline_sync_entity_state' => [
            'entity_type' => "TEXT DEFAULT ''",
            'entity_id' => "TEXT DEFAULT ''",
            'payload_sha256' => "TEXT DEFAULT ''",
            'updated_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
        ],
        'offline_sync_runtime' => [
            'last_tick_at' => 'TEXT DEFAULT NULL',
            'last_success_at' => 'TEXT DEFAULT NULL',
            'last_error' => "TEXT DEFAULT ''",
            'updated_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
        ],
        'setari_platforma' => [
            'comunicare_anaf' => 'INTEGER DEFAULT 0',
            'mod_touch' => 'INTEGER DEFAULT 0',
            'activare_listener' => 'INTEGER DEFAULT 0',
            'cu_imprimanta' => 'INTEGER DEFAULT 1',
            'autologin_restaurant' => 'INTEGER DEFAULT 0',
            'listare_automata_comenzi_site' => 'INTEGER DEFAULT 1',
            'omite_listare_bar_la_trimitere' => 'INTEGER DEFAULT 0',
        ],
        'mapare_woo_pos' => [
            'woo_product_id' => 'INTEGER NOT NULL DEFAULT 0',
            'woo_tip' => "TEXT NOT NULL DEFAULT 'simple'",
            'cod_produs' => 'INTEGER NOT NULL DEFAULT 0',
            'nume_woo' => "TEXT NOT NULL DEFAULT ''",
            'nume_pos' => "TEXT NOT NULL DEFAULT ''",
            'metoda_mapare' => "TEXT NOT NULL DEFAULT 'manual'",
            'activ' => 'INTEGER NOT NULL DEFAULT 1',
            'observatii_interne' => 'TEXT DEFAULT NULL',
            'created_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
        ],
        'woo_order_imports' => [
            'woo_order_id' => 'INTEGER NOT NULL DEFAULT 0',
            'woo_order_number' => "TEXT NOT NULL DEFAULT ''",
            'note_nrbon' => 'INTEGER NOT NULL DEFAULT 0',
            'locatie' => 'INTEGER NOT NULL DEFAULT 0',
            'imported_by' => 'INTEGER NOT NULL DEFAULT 0',
            'imported_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
            'payload_hash' => 'TEXT DEFAULT NULL',
            'listat_auto_de_pe_site' => 'INTEGER NOT NULL DEFAULT 0',
        ],
        'date_firma' => [
            'den_ent' => "TEXT DEFAULT ''",
            'cod_fiscal' => "TEXT DEFAULT ''",
            'denumire_firma' => "TEXT DEFAULT ''",
            'pseudonim_firma' => "TEXT DEFAULT ''",
            'cui' => "TEXT DEFAULT ''",
            'nr_reg_com' => "TEXT DEFAULT ''",
            'sediu' => "TEXT DEFAULT ''",
            'judet' => "TEXT DEFAULT ''",
            'localitate' => "TEXT DEFAULT ''",
            'banca' => "TEXT DEFAULT ''",
            'cont_banca' => "TEXT DEFAULT ''",
            'cap_soc' => 'REAL DEFAULT 0',
            'numar_zile_scadenta' => 'INTEGER DEFAULT 0',
            'serie_factura_comenzi' => "TEXT DEFAULT ''",
            'serie_factura_implicita' => "TEXT DEFAULT ''",
            'nr_zile_activ_storn' => 'INTEGER DEFAULT 0',
            'logo' => "TEXT DEFAULT ''",
            'cota_tva_predefinita' => 'INTEGER DEFAULT 0',
            'url' => "TEXT DEFAULT ''",
            'email' => "TEXT DEFAULT ''",
            'telefon' => "TEXT DEFAULT ''",
            'tva' => 'INTEGER DEFAULT 0',
            'token_anaf' => "TEXT DEFAULT ''",
            'metoda_plata_implicita' => "TEXT DEFAULT ''",
            'serie_stornare' => "TEXT DEFAULT ''",
            'text_subsol_factura' => "TEXT DEFAULT ''",
            'serie_casa_marcat' => "TEXT DEFAULT ''",
            'nui' => 'INTEGER DEFAULT 0',
            'serie_memorie_fiscala' => "TEXT DEFAULT ''",
            'mod_listare' => "TEXT DEFAULT 'simplu'",
            'conducator_entitate' => "TEXT DEFAULT ''",
            'vanzare_sub_stoc' => 'INTEGER DEFAULT 1',
            'ajustare_adaos' => 'INTEGER DEFAULT 0',
            'adresa' => "TEXT DEFAULT ''",
        ],
        'det_note' => [
            'identificator_offline' => 'TEXT DEFAULT NULL',
            'nume_produs' => "TEXT DEFAULT ''",
            'importat_din_site' => 'INTEGER DEFAULT NULL',
            'departament_listare' => 'TEXT DEFAULT NULL',
        ],
        'note' => [
            'identificator_offline' => 'TEXT DEFAULT NULL',
            'serie_casa_marcat' => "TEXT DEFAULT ''",
            'nui' => 'INTEGER DEFAULT 0',
            'serie_memorie_fiscala' => "TEXT DEFAULT ''",
        ],
        'discounturi_acordate' => [
            'identificator_offline' => 'TEXT DEFAULT NULL',
        ],
        'com_tableta' => [
            'serie' => "TEXT DEFAULT ''",
            'data_bon' => "TEXT DEFAULT ''",
            'ora_bon' => "TEXT DEFAULT ''",
            'valoare_vanzare_cu_tva' => 'REAL DEFAULT 0',
            'tva_colectata' => 'REAL DEFAULT 0',
            'discount' => 'REAL DEFAULT 0',
            'operator' => 'INTEGER DEFAULT 0',
            'numerar' => 'REAL DEFAULT 0',
            'card' => 'REAL DEFAULT 0',
            'tichete' => 'REAL DEFAULT 0',
            'rest' => 'REAL DEFAULT 0',
            'protocol' => 'REAL DEFAULT 0',
            'glovo' => 'REAL DEFAULT 0',
            'virament_bancar' => 'REAL DEFAULT 0',
            'cif_client' => "TEXT DEFAULT ''",
            'cod_masa' => 'INTEGER DEFAULT 0',
            'stare' => "TEXT DEFAULT 'NEFINALIZATA'",
            'status' => "TEXT DEFAULT 'N'",
            'cod_inchidere' => 'INTEGER DEFAULT 0',
            'tableta' => 'INTEGER DEFAULT 1',
            'locatie' => 'INTEGER DEFAULT 0',
            'nr_raport_z' => 'INTEGER DEFAULT 0',
            'data_deschidere' => "TEXT DEFAULT ''",
            'listat_nota_plata' => 'INTEGER DEFAULT 0',
            'owner_operator_id' => 'INTEGER DEFAULT 0',
            'owner_operator_name' => "TEXT DEFAULT ''",
            'payload_hash' => "TEXT DEFAULT ''",
            'fetched_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
            'imported_note_nrbon' => 'INTEGER DEFAULT 0',
            'imported_at' => 'TEXT DEFAULT NULL',
            'online_ack_status' => "TEXT DEFAULT 'not_ready'",
            'online_ack_attempts' => 'INTEGER DEFAULT 0',
            'online_ack_error' => "TEXT DEFAULT ''",
            'online_acknowledged_at' => 'TEXT DEFAULT NULL',
        ],
        'det_com_tableta' => [
            'nr_bon' => 'INTEGER DEFAULT 0',
            'cod_p' => 'INTEGER DEFAULT 0',
            'nume_produs' => "TEXT DEFAULT ''",
            'cantitate' => 'REAL DEFAULT 0',
            'cota_tva' => 'REAL DEFAULT 0',
            'tva_col' => 'REAL DEFAULT 0',
            'pret_vanzare' => 'REAL DEFAULT 0',
            'valoare_vanzare' => 'REAL DEFAULT 0',
            'valoare_vanzare_cu_tva' => 'REAL DEFAULT 0',
            'discount' => 'REAL DEFAULT 0',
            'pachet' => 'INTEGER DEFAULT 0',
            'preparat' => 'INTEGER DEFAULT 0',
            't_list' => 'INTEGER DEFAULT 0',
            'data' => "TEXT DEFAULT ''",
            'ora' => "TEXT DEFAULT ''",
            'cod_meniu' => 'INTEGER DEFAULT 0',
            'observatie_produs' => "TEXT DEFAULT ''",
            'preluat_osp' => 'INTEGER DEFAULT 0',
            'prioritate' => 'INTEGER DEFAULT 0',
            'online_id_vanz' => 'INTEGER DEFAULT 0',
            'departament_listare' => 'TEXT DEFAULT NULL',
        ],
        'offline_tablet_sync_runtime' => [
            'last_pull_at' => 'TEXT DEFAULT NULL',
            'last_pull_success_at' => 'TEXT DEFAULT NULL',
            'last_ack_at' => 'TEXT DEFAULT NULL',
            'last_ack_success_at' => 'TEXT DEFAULT NULL',
            'last_error' => "TEXT DEFAULT ''",
            'last_orders_received' => 'INTEGER DEFAULT 0',
            'last_orders_inserted' => 'INTEGER DEFAULT 0',
            'last_orders_updated' => 'INTEGER DEFAULT 0',
            'updated_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
        ],
        'offline_tablet_sync_logs' => [
            'action' => "TEXT DEFAULT ''",
            'status' => "TEXT DEFAULT ''",
            'data_ora' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
            'received_count' => 'INTEGER DEFAULT 0',
            'inserted_count' => 'INTEGER DEFAULT 0',
            'updated_count' => 'INTEGER DEFAULT 0',
            'acknowledged_count' => 'INTEGER DEFAULT 0',
            'http_code' => 'INTEGER DEFAULT 0',
            'message' => "TEXT DEFAULT ''",
        ],
    ];

    foreach ($tables as $table => $columns) {
        $existing = restaurant_sqlite_table_columns($pdo, $table);
        foreach ($columns as $column => $definition) {
            if (isset($existing[$column])) {
                continue;
            }
            $pdo->exec('ALTER TABLE "' . str_replace('"', '""', $table) . '" ADD COLUMN "' . str_replace('"', '""', $column) . '" ' . $definition);
        }
    }

    $pdo->exec("UPDATE date_firma SET denumire_firma = den_ent WHERE COALESCE(denumire_firma, '') = '' AND COALESCE(den_ent, '') <> ''");
    $pdo->exec("UPDATE date_firma SET adresa = sediu WHERE COALESCE(adresa, '') = '' AND COALESCE(sediu, '') <> ''");
    $pdo->exec("UPDATE com_tableta SET status = CASE stare WHEN 'TRIMISA' THEN 'S' WHEN 'IMPORTATA' THEN 'I' ELSE status END WHERE COALESCE(status, '') IN ('', 'N')");
    $pdo->exec("UPDATE com_tableta SET stare = CASE status WHEN 'S' THEN 'TRIMISA' WHEN 'I' THEN 'IMPORTATA' ELSE stare END WHERE COALESCE(stare, '') IN ('', 'NEFINALIZATA')");
}
