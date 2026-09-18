<?php
$path='C:/xampp/htdocs/github/instalari_offline_ecogest/dailycoffee_cu_mini_admin/api_offline_ecogest_dailycoffee/db_local/pos.db';
$db=new PDO('sqlite:'.$path);
foreach(['produse_servicii','produse_servicii_locatii','categorii','categorii_locatii'] as $t){
    echo "TABLE $t\n";
    echo json_encode($db->query("PRAGMA table_info('".$t."')")->fetchAll(PDO::FETCH_ASSOC),JSON_UNESCAPED_UNICODE)."\n";
    echo "COUNT ".$db->query("SELECT COUNT(*) FROM `".$t."`")->fetchColumn()."\n";
}
