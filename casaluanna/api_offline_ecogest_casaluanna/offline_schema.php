<?php
function casa_schema(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS casa_meta (name TEXT PRIMARY KEY,value TEXT NOT NULL)');
    $version=(int)$db->query("SELECT value FROM casa_meta WHERE name='schema_version'")->fetchColumn();
    if($version>=2)return;
    $db->beginTransaction();
    try{
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
        $db->exec("INSERT OR REPLACE INTO casa_meta(name,value) VALUES('schema_version','2')");$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
