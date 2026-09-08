<?php
require_once __DIR__.'/database_connection.php';
$cui=preg_replace('/^RO/i','',trim((string)($_GET['cui']??'')));
$row=casa_one($pdo,"SELECT * FROM clienti WHERE REPLACE(UPPER(cod_fiscal),'RO','')=? LIMIT 1",[$cui]);
if(!$row)casa_json(['error'=>'Firma nu există în nomenclatorul local. Completați manual datele beneficiarului.']);
casa_json($row);
