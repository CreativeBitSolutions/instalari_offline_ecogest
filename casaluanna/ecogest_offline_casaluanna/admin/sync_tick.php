<?php
require_once __DIR__.'/offline_sync.php';casa_session();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')casa_json(['message'=>'Metodă nepermisă.'],405);
casa_auth();session_write_close();
try{casa_json(casa_sync_tick());}catch(Throwable $e){error_log('Casa Luanna sync: '.$e->getMessage());casa_json(['message'=>'Sincronizarea nu a fost confirmată. Verificați pagina Sincronizare.'],503);}
