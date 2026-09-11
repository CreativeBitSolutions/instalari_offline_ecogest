<?php
function casa_schema(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS casa_meta (name TEXT PRIMARY KEY,value TEXT NOT NULL)');
    $version=(int)$db->query("SELECT value FROM casa_meta WHERE name='schema_version'")->fetchColumn();
    if($version>=6)return;
    if($version>=5){
        $db->beginTransaction();
        try{
            $identityDefinitions=['nui'=>'INTEGER NOT NULL DEFAULT 0','serie_memorie_fiscala'=>"TEXT NOT NULL DEFAULT ''"];
            foreach(['date_firma','loc_mese_12','rapoarte_z','note','inchideri_r_12','miscari'] as $table){
                $tableExists=(int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=".$db->quote($table))->fetchColumn();
                if($tableExists===0)continue;
                $columns=array_column($db->query("PRAGMA table_info('".$table."')")->fetchAll(PDO::FETCH_ASSOC),'name');
                foreach($identityDefinitions as $column=>$definition){
                    if(!in_array($column,$columns,true))$db->exec('ALTER TABLE "'.$table.'" ADD COLUMN "'.$column.'" '.$definition);
                }
            }
            $db->exec("INSERT OR REPLACE INTO casa_meta(name,value) VALUES('schema_version','6')");
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return;
    }
    $db->beginTransaction();
    try{
        $identityDefinitions = [
            'nui' => 'INTEGER NOT NULL DEFAULT 0',
            'serie_memorie_fiscala' => "TEXT NOT NULL DEFAULT ''",
        ];
        foreach(['date_firma','loc_mese_12','rapoarte_z','note','inchideri_r_12','miscari'] as $table){
            $tableExists = (int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=".$db->quote($table))->fetchColumn();
            if($tableExists===0)continue;
            $columns = array_column($db->query("PRAGMA table_info('".$table."')")->fetchAll(PDO::FETCH_ASSOC),'name');
            foreach($identityDefinitions as $column=>$definition){
                if(!in_array($column,$columns,true)){
                    $db->exec('ALTER TABLE "'.$table.'" ADD COLUMN "'.$column.'" '.$definition);
                }
            }
        }
        $db->exec("CREATE TABLE IF NOT EXISTS casa_invoices (id_factura INTEGER PRIMARY KEY,state TEXT NOT NULL DEFAULT 'draft',revision INTEGER NOT NULL DEFAULT 0,online_id INTEGER,hash TEXT NOT NULL DEFAULT '',error TEXT NOT NULL DEFAULT '')");
        $db->exec("CREATE TABLE IF NOT EXISTS casa_outbox (event_uuid TEXT PRIMARY KEY,id_factura INTEGER NOT NULL,revision INTEGER NOT NULL,payload TEXT NOT NULL,hash TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',attempts INTEGER NOT NULL DEFAULT 0,next_attempt INTEGER NOT NULL DEFAULT 0,error TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL,ack_at TEXT,UNIQUE(id_factura,revision))");
        if($version===0)$db->exec("INSERT OR IGNORE INTO casa_invoices(id_factura,state,online_id) SELECT id_factura,'historical',id_factura FROM facturi");
        $columns=array_column($db->query("PRAGMA table_info('casa_invoices')")->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('origin',$columns,true))$db->exec("ALTER TABLE casa_invoices ADD COLUMN origin TEXT NOT NULL DEFAULT 'local'");
        $db->exec("UPDATE casa_invoices SET origin='historical' WHERE state='historical'");
        $db->exec("INSERT OR IGNORE INTO casa_meta(name,value) SELECT 'receipt_baseline',CAST(COALESCE(MAX(id_incasare),0) AS TEXT) FROM incasari");
        $db->exec("CREATE TRIGGER IF NOT EXISTS casa_new_invoice AFTER INSERT ON facturi BEGIN INSERT OR IGNORE INTO casa_invoices(id_factura) VALUES(NEW.id_factura); END");
        foreach(['casa_outbox_due ON casa_outbox(status,next_attempt)','casa_vanzari_invoice ON vanzari(id_factura)','casa_incasari_invoice ON incasari(id_factura)','casa_miscari_line ON miscari(id_vanz_fact)','casa_facturi_number ON facturi(serie_factura,nr_factura)'] as $index)$db->exec('CREATE INDEX IF NOT EXISTS '.$index);
        $q=$db->prepare('INSERT OR IGNORE INTO casa_meta(name,value) VALUES(?,?)');$q->execute(['installation_uuid',bin2hex(random_bytes(16))]);
        if(!in_array('finalized',$columns,true)){
            $db->exec("ALTER TABLE casa_invoices ADD COLUMN finalized INTEGER NOT NULL DEFAULT 0");
            $db->exec("UPDATE casa_invoices SET finalized=1 WHERE state IN ('queued','synced')");
        }
        if(!in_array('authority',$columns,true))$db->exec("ALTER TABLE casa_invoices ADD COLUMN authority TEXT NOT NULL DEFAULT 'local'");
        $db->exec("UPDATE casa_invoices SET authority='online' WHERE origin='historical'");
        $db->exec('CREATE TABLE IF NOT EXISTS casa_remote_fingerprints(online_id INTEGER PRIMARY KEY,hash TEXT NOT NULL)');
        $db->exec('CREATE TABLE IF NOT EXISTS casa_remote_status(id_factura INTEGER PRIMARY KEY,payload TEXT NOT NULL,checked_at TEXT NOT NULL)');
        $db->exec('CREATE TABLE IF NOT EXISTS casa_remote_rows(entity TEXT NOT NULL,online_id INTEGER NOT NULL,local_id INTEGER NOT NULL,id_factura INTEGER NOT NULL,PRIMARY KEY(entity,online_id))');
        $db->exec('CREATE TABLE IF NOT EXISTS casa_remote_archive(entity TEXT NOT NULL,local_id INTEGER NOT NULL,payload TEXT NOT NULL,archived_at TEXT NOT NULL)');
        $db->exec("CREATE VIEW IF NOT EXISTS casa_online_series AS SELECT * FROM serii_documente WHERE NOT EXISTS(SELECT 1 FROM casa_meta WHERE name='online_members_serii_documente') OR id_serie IN (SELECT value FROM json_each((SELECT value FROM casa_meta WHERE name='online_members_serii_documente')))");
        $db->exec("CREATE VIEW IF NOT EXISTS casa_online_company AS SELECT * FROM date_firma WHERE NOT EXISTS(SELECT 1 FROM casa_meta WHERE name='online_members_date_firma') OR id IN (SELECT value FROM json_each((SELECT value FROM casa_meta WHERE name='online_members_date_firma')))");
        foreach(['vanzari'=>'id_vanz','incasari'=>'id_incasare','miscari'=>'id','stornari'=>'id_stornare'] as $table=>$pk){
            $join=$table==='miscari'?'JOIN vanzari v ON v.id_vanz=t.id_vanz_fact JOIN casa_invoices c ON c.id_factura=v.id_factura':'JOIN casa_invoices c ON c.id_factura=t.id_factura';
            $cutoff=$table==='incasari'?" AND t.id_incasare<=CAST((SELECT value FROM casa_meta WHERE name='receipt_baseline') AS INTEGER)":'';
            $db->exec("INSERT OR IGNORE INTO casa_remote_rows SELECT '$table',t.$pk,t.$pk,c.id_factura FROM $table t $join WHERE c.origin='historical'".$cutoff);
        }
        $db->exec('CREATE TABLE IF NOT EXISTS casa_local_receipts(id_incasare INTEGER PRIMARY KEY)');
        $db->exec("INSERT OR IGNORE INTO casa_local_receipts SELECT i.id_incasare FROM incasari i JOIN casa_invoices c ON c.id_factura=i.id_factura WHERE c.origin='historical' AND i.id_incasare>CAST((SELECT value FROM casa_meta WHERE name='receipt_baseline') AS INTEGER) AND NOT EXISTS(SELECT 1 FROM casa_remote_rows r WHERE r.entity='incasari' AND r.local_id=i.id_incasare)");
        $db->exec("CREATE TRIGGER IF NOT EXISTS casa_new_local_receipt AFTER INSERT ON incasari WHEN NOT EXISTS(SELECT 1 FROM casa_meta WHERE name='pull_running') AND EXISTS(SELECT 1 FROM casa_invoices WHERE id_factura=NEW.id_factura AND origin='historical') BEGIN INSERT OR IGNORE INTO casa_local_receipts VALUES(NEW.id_incasare); END");
        $db->exec("INSERT OR REPLACE INTO casa_meta(name,value) VALUES('schema_version','6')");$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
