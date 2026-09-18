<?php
$path='C:/xampp/htdocs/github/instalari_offline_ecogest/dailycoffee_cu_mini_admin/api_offline_ecogest_dailycoffee/db_local/pos.db';
$db=new PDO('sqlite:'.$path);
$tables=$db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
foreach($tables as $table){
    echo "TABLE $table\n";
    $safe=str_replace("'","''",$table);
    $cols=$db->query("PRAGMA table_info('{$safe}')")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($cols,JSON_UNESCAPED_UNICODE)."\n";
    $count=$db->query('SELECT COUNT(*) FROM "'.str_replace('"','""',$table).'"')->fetchColumn();
    echo "COUNT $count\n";
}
